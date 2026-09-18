<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AuthorResource as BaseAuthorResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Illuminate\Database\Eloquent\Model;
use stdClass;

/**
 * Tests for the interaction between Api::authorize() rules and afterAdd/afterUpdate.
 *
 * The post-state check runs after afterAdd/afterUpdate, so a row that becomes
 * authorized only through these hooks must still pass.
 */
class ApiAuthorizeSavePostStateTest extends ApiResourcesEloquentTest
{
    protected function setUp(): void
    {
        parent::setUp();
        PostStateAuthorResource::$renameInAfterAddTo = null;
        PostStateAuthorResource::$renameInAfterUpdateTo = null;
    }

    // -- afterAdd --

    public function test_add_row_passes_post_state_after_afterAdd_makes_it_authorized()
    {
        // The row is created with name 'pending' — that fails the rule.
        // afterAdd renames it to 'ok approved' — that passes. Post-state
        // check runs after afterAdd, so the save succeeds.
        PostStateAuthorResource::$renameInAfterAddTo = 'ok approved';

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(PostStateAuthorResource::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'data' => ['name' => 'pending', 'email' => 'pending@test'],
            'fields' => ['name' => true]
        ]);

        $this->assertEquals('ok approved', $result['data']['name']);
        $this->assertEquals(1, Author::count());
    }

    public function test_add_row_fails_post_state_when_afterAdd_does_not_help()
    {
        // No afterAdd rename — row stays outside the rule.
        $this->expectException(NotFoundException::class);

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'data' => ['name' => 'blocked', 'email' => 'blocked@test'],
                'fields' => ['name' => true]
            ]);
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    // -- afterUpdate --

    public function test_update_row_passes_post_state_after_afterUpdate_makes_it_authorized()
    {
        $author = Author::factory()->create(['name' => 'ok initial']);

        // The update writes 'blocked-mid' — that fails the rule.
        // afterUpdate renames it to 'ok final' — that passes.
        PostStateAuthorResource::$renameInAfterUpdateTo = 'ok final';

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->getResources()->add(PostStateAuthorResource::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'params' => ['id' => $author->id],
            'data' => ['name' => 'blocked-mid'],
            'fields' => ['name' => true]
        ]);

        $this->assertEquals('ok final', $result['data']['name']);
        $this->assertEquals('ok final', Author::find($author->id)->name);
    }

    public function test_update_row_fails_post_state_when_afterUpdate_does_not_help()
    {
        $author = Author::factory()->create(['name' => 'ok initial']);

        // Update writes 'blocked' — afterUpdate does nothing — post-state fails.
        $this->expectException(NotFoundException::class);

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $author->id],
                'data' => ['name' => 'blocked'],
                'fields' => ['name' => true]
            ]);
        } finally {
            $this->assertEquals('ok initial', Author::find($author->id)->name);
        }
    }

    // -- basic list/get/save with configureAuth --

    public function test_list_filtered_by_type_rule()
    {
        Author::factory()->count(2)->create(['name' => 'ok one']);
        Author::factory()->create(['name' => 'blocked one']);

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['meta']['count_all']);
    }

    public function test_get_forbidden_id_throws_not_found()
    {
        Author::factory()->create(['name' => 'ok allowed']);
        $forbidden = Author::factory()->create(['name' => 'blocked forbidden']);

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        $this->expectException(NotFoundException::class);

        $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $forbidden->id],
            'fields' => ['name' => true]
        ]);
    }

    public function test_save_forbidden_id_throws_not_found()
    {
        Author::factory()->create(['name' => 'ok allowed']);
        $forbidden = Author::factory()->create(['name' => 'blocked forbidden']);

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

        $originalName = $forbidden->name;

        $this->expectException(NotFoundException::class);

        try {
            $api->requestFromInput([
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $forbidden->id],
                'data' => ['name' => 'Should Not Update'],
                'fields' => ['name' => true]
            ]);
        } finally {
            $this->assertEquals($originalName, Author::find($forbidden->id)->name);
        }
    }

    public function test_resource_rule_filters_list_and_count_all()
    {
        Author::factory()->count(3)->create();
        $allowedIds = [Author::first()->id, Author::skip(1)->first()->id];

        $api = (new ApiResources())->getApi(BlogApi::class);
        $api->authorize(BaseAuthorResource::class,
            fn (EloquentAuthContext $c) => $c->query()->whereIn($c->getTablePrefix() . '.id', $allowedIds));

        $result = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['meta']['count_all']);
    }
}

/**
 * Resource with afterAdd/afterUpdate that renames the row.
 */
class PostStateAuthorResource extends BaseAuthorResource
{
    public static ?string $renameInAfterAddTo = null;
    public static ?string $renameInAfterUpdateTo = null;

    protected function afterAdd(Model $model, array $saveFields, stdClass $meta): void
    {
        if (self::$renameInAfterAddTo !== null) {
            $model->name = self::$renameInAfterAddTo;
            $model->save();
        }
    }

    protected function afterUpdate(Model $model, array $saveFields, stdClass $meta): void
    {
        if (self::$renameInAfterUpdateTo !== null) {
            $model->name = self::$renameInAfterUpdateTo;
            $model->save();
        }
    }
}
