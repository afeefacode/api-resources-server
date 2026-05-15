<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AuthorResource as ResourcesAuthorResource;
use Illuminate\Database\Eloquent\Builder;

class ModelResourceAuthorizeTest extends ApiResourcesEloquentTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AuthorizeAuthorResource::$allowedIds = [];
        AuthorizeAuthorResource::$allowedNames = [];
    }

    public function test_authorize_filters_list_and_count_all()
    {
        $authors = Author::factory()
            ->count(3)
            ->has(Article::factory()->count(2))
            ->create();

        AuthorizeAuthorResource::$allowedIds = [$authors[0]->id, $authors[1]->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        ['data' => $data, 'meta' => $meta] = $result;

        $this->assertCount(2, $data);
        $this->assertEquals(2, $meta['count_all']);
        $this->assertEquals(2, $meta['count_filter']);
        $this->assertEquals(2, $meta['count_search']);
    }

    public function test_authorize_get_allowed_id_returns_model()
    {
        $authors = Author::factory()->count(3)->create();
        AuthorizeAuthorResource::$allowedIds = [$authors[0]->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $authors[0]->id],
            'fields' => ['name' => true]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals($authors[0]->name, $data['name']);
    }

    public function test_authorize_get_forbidden_id_throws_not_found()
    {
        $authors = Author::factory()->count(3)->create();
        AuthorizeAuthorResource::$allowedIds = [$authors[0]->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $this->expectException(NotFoundException::class);

        $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $authors[1]->id],
            'fields' => ['name' => true]
        ]);
    }

    public function test_authorize_save_allowed_id_updates_model()
    {
        $authors = Author::factory()->count(3)->create();
        AuthorizeAuthorResource::$allowedIds = [$authors[0]->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'params' => ['id' => $authors[0]->id],
            'data' => ['name' => 'Updated Name'],
            'fields' => ['name' => true]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('Updated Name', Author::find($authors[0]->id)->name);
    }

    public function test_authorize_save_forbidden_id_throws_not_found()
    {
        $authors = Author::factory()->count(3)->create();
        AuthorizeAuthorResource::$allowedIds = [$authors[0]->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $originalName = $authors[1]->name;

        $this->expectException(NotFoundException::class);

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $authors[1]->id],
                'data' => ['name' => 'Should Not Update'],
                'fields' => ['name' => true]
            ]);
        } finally {
            // Verify the forbidden record was not modified
            $this->assertEquals($originalName, Author::find($authors[1]->id)->name);
        }
    }

    public function test_authorize_default_no_op_does_not_filter()
    {
        Author::factory()->count(3)->create();

        $api = (new ApiResources())->getApi(BlogApi::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        ['data' => $data, 'meta' => $meta] = $result;

        $this->assertCount(3, $data);
        $this->assertEquals(3, $meta['count_all']);
    }

    public function test_authorize_add_allowed_attributes_creates_model()
    {
        AuthorizeAuthorResource::$allowedNames = ['Allowed Writer'];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'data' => [
                'name' => 'Allowed Writer',
                'email' => 'allowed@writer'
            ],
            'fields' => ['name' => true]
        ]);

        ['data' => $data] = $result;

        $this->assertEquals('Allowed Writer', $data['name']);
        $this->assertEquals(1, Author::count());
    }

    public function test_authorize_add_forbidden_attributes_throws_and_rolls_back()
    {
        AuthorizeAuthorResource::$allowedNames = ['Allowed Writer'];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $this->expectException(NotFoundException::class);

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'data' => [
                    'name' => 'Forbidden Writer',
                    'email' => 'forbidden@writer'
                ],
                'fields' => ['name' => true]
            ]);
        } finally {
            // Transaction must have rolled back — no row left behind.
            $this->assertEquals(0, Author::count());
        }
    }

    public function test_authorize_update_mutation_into_forbidden_throws_and_rolls_back()
    {
        $author = Author::factory()->create(['name' => 'Allowed Writer']);

        // Initial state is reachable; update tries to mutate the row out of
        // the reachable set. Post-state check must catch that.
        AuthorizeAuthorResource::$allowedNames = ['Allowed Writer'];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(AuthorizeAuthorResource::class);

        $this->expectException(NotFoundException::class);

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $author->id],
                'data' => ['name' => 'Forbidden Writer'],
                'fields' => ['name' => true]
            ]);
        } finally {
            // Rollback: original name preserved.
            $this->assertEquals('Allowed Writer', Author::find($author->id)->name);
        }
    }
}

class AuthorizeAuthorResource extends ResourcesAuthorResource
{
    public static array $allowedIds = [];
    public static array $allowedNames = [];

    protected function authorize(Builder $query): void
    {
        if (!empty(self::$allowedIds)) {
            $query->whereIn('id', self::$allowedIds);
        }
        if (!empty(self::$allowedNames)) {
            $query->whereIn('name', self::$allowedNames);
        }
    }
}
