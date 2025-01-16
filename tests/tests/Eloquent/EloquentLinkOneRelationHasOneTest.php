<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;

use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Profile;

class EloquentLinkOneRelationHasOneTest extends ApiResourcesEloquentTest
{
    public function test_set()
    {
        $profile = Profile::factory()->create();
        $author = Author::factory()->create(['name' => 'author1']);

        $this->save(
            id: $profile->id,
            data: [
                'author' => [
                    'id' => $author->id
                ]
            ]
        );

        $this->assertAuthor($profile->id, ['1', 'author1']);
    }

    public function test_set_not_exists()
    {
        $profile = Profile::factory()->create();

        $this->save(
            id: $profile->id,
            data: [
                'author' => [
                    'id' => 'does_not_exist'
                ]
            ]
        );

        $this->assertAuthor($profile->id, []);
    }

    public function test_set_empty()
    {
        $profile = Profile::factory()
            ->has(Author::factory(['name' => 'author1']))
            ->create();

        $this->assertAuthor($profile->id, ['1', 'author1']);

        $authorId = $profile->author->id;

        $author = Author::first();

        $this->assertEquals($authorId, $author->id);

        $this->save(
            id: $profile->id,
            data: [
                'author' => null
            ]
        );

        $this->assertAuthor($profile->id, []);

        $author = Author::first();

        $this->assertEquals($authorId, $author->id);
    }

    public function test_create_set()
    {
        $author = Author::factory()->create(['name' => 'author1']);

        $profile = $this->create([
            'author' => [
                'id' => $author->id
            ]
        ]);

        $this->assertAuthor($profile->id, ['1', 'author1']);
    }

    public function test_create_set_not_exists()
    {
        $profile = $this->create([
            'author' => [
                'id' => 'does_not_exist'
            ]
        ]);

        $this->assertAuthor($profile->id, []);
    }

    public function test_create_set_empty()
    {
        $profile = $this->create([
            'author' => null
        ]);

        $this->assertAuthor($profile->id, []);

        $profile = $this->create();

        $this->assertAuthor($profile->id, []);
    }

    protected function save(?string $id = null, array $data = []): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.ProfileResource',
            'action' => 'save',
            'params' => [
                'id' => $id
            ],
            'data' => $data
        ]);
    }

    protected function create(array $data = []): Profile
    {
        ['data' => $profile] = $this->save(null, [
            'about_me' => 'about author1',
            ...$data
        ]);
        return $profile;
    }

    protected function get(string $id): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.ProfileResource',
            'action' => 'get',
            'params' => [
                'id' => $id
            ],
            'fields' => [
                'author' => [
                    'name' => true
                ]
            ]
        ]);
    }

    protected function assertAuthor(?string $id, ?array $author)
    {
        $result = $this->get($id);

        $data = $result['data'];

        if ($author) {
            $this->assertEquals($author[0], $data['author']['id']);
            $this->assertEquals($author[1], $data['author']['name']);
        } else {
            $this->assertNull($data['author']);
        }
    }
}
