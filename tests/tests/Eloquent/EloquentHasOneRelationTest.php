<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Profile;

use function Afeefa\ApiResources\Test\toArray;

class EloquentHasOneRelationTest extends ApiResourcesEloquentTest
{
    public function test_set()
    {
        $author = Author::factory()->create();

        $this->save(
            id: $author->id,
            data: [
                'profile' => [
                    'about_me' => 'about1'
                ]
            ]
        );

        $this->assertProfile($author->id, ['1', 'about1']);
    }

    public function test_set_update()
    {
        $author = $this->createAuthorWithProfile();

        $this->save(
            id: $author->id,
            data: [
                'profile' => [
                    'id' => '1',
                    'about_me' => 'about1_update'
                ]
            ]
        );

        $this->assertProfile($author->id, ['1', 'about1_update']);
    }

    public function test_set_update_of_other()
    {
        $author = Author::factory()->create();

        Profile::factory()->create(['about_me' => 'about1']);

        $this->save(
            id: $author->id,
            data: [
                'profile' => [
                    'id' => '1',
                    'about_me' => 'about1_update'
                ]
            ]
        );

        $this->assertProfile($author->id, ['2', 'about1_update']);

        $this->assertEquals('about1', Profile::find('1')->about_me);
    }

    public function test_set_update_not_exists()
    {
        $author = Author::factory()->create();

        $this->save(
            id: $author->id,
            data: [
                'profile' => [
                    'id' => 'does_not_exist',
                    'about_me' => 'about1'
                ]
            ]
        );

        $this->assertProfile($author->id, ['1', 'about1']);
    }

    public function test_set_empty()
    {
        $author = $this->createAuthorWithProfile();

        $this->save(
            id: $author->id,
            data: [
                'profile' => null
            ]
        );

        $this->assertProfile($author->id, []);
    }

    public function test_create_set()
    {
        $author = $this->create([
            'profile' => [
                'about_me' => 'about1'
            ]
        ]);

        $this->assertProfile($author->id, ['1', 'about1']);
    }

    public function test_create_set_one_update_of_other()
    {
        $author = Author::factory()->create();

        Profile::factory()->create(['about_me' => 'about1']);

        $author = $this->create([
            'profile' => [
                'id' => '1',
                'about_me' => 'about1_update'
            ]
        ]);

        $this->assertProfile($author->id, ['2', 'about1_update']);

        $this->assertEquals('about1', Profile::find('1')->about_me);
    }

    public function test_create_set_one_update_not_exists()
    {
        $author = $this->create([
            'profile' => [
                'id' => 'does_not_exist',
                'about_me' => 'about3'
            ]
        ]);

        $this->assertProfile($author->id, ['1', 'about3']);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'profile' => null
        ]);

        $this->assertProfile($author->id, []);

        $author = $this->create();

        $this->assertProfile($author->id, []);
    }

    protected function createAuthorWithProfile(): Author
    {
        $author = Author::factory()
            ->for(Profile::factory()->create(['about_me' => 'about1']))
            ->create();

        $this->assertProfile($author->id, ['1', 'about1']);

        return $author;
    }

    protected function save(?string $id = null, array $data = []): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'params' => [
                'id' => $id
            ],
            'data' => $data
        ]);
    }

    protected function create(array $data = []): Author
    {
        ['data' => $author] = $this->save(null, [
            'name' => 'author1',
            'email' => 'mail@author1',
            ...$data
        ]);
        return $author;
    }

    protected function get(string $id): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => [
                'id' => $id
            ],
            'fields' => [
                'profile' => [
                    'about_me' => true
                ]
            ]
        ]);
    }

    protected function assertProfile(string $id, ?array $profile)
    {
        $result = $this->get($id);

        // debug_dump(toArray($result));

        $data = $result['data'];

        if ($profile) {
            $this->assertEquals($profile[0], $data['profile']['id']);
            $this->assertEquals($profile[1], $data['profile']['about_me']);
        } else {
            $this->assertNull($data['profile']);
        }
    }
}
