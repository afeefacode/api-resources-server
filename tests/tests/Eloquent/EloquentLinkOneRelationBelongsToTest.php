<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;

use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;

class EloquentLinkOneRelationBelongsToTest extends ApiResourcesEloquentTest
{
    public function test_set()
    {
        $author = Author::factory()->create();

        Tag::factory()->create(['name' => 'tag1']);

        $this->save(
            id: $author->id,
            data: [
                'featured_tag' => [
                    'id' => '1'
                ]
            ]
        );

        $this->assertFeaturedTag($author->id, ['1', 'tag1']);
    }

    public function test_set_not_exists()
    {
        $author = Author::factory()->create();

        $this->save(
            id: $author->id,
            data: [
                'featured_tag' => [
                    'id' => 'does_not_exist'
                ]
            ]
        );

        $this->assertFeaturedTag($author->id, []);
    }

    public function test_set_empty()
    {
        $author = Author::factory()
            ->for(Tag::factory()->create(['name' => 'tag1']), 'featured_tag')
            ->create();

        $this->assertFeaturedTag($author->id, ['1', 'tag1']);

        $this->save(
            id: $author->id,
            data: [
                'featured_tag' => null
            ]
        );

        $this->assertFeaturedTag($author->id, []);
    }

    public function test_create_set()
    {
        Tag::factory()->create(['name' => 'tag1']);

        $author = $this->create([
            'featured_tag' => [
                'id' => '1'
            ]
        ]);

        $this->assertFeaturedTag($author->id, ['1', 'tag1']);
    }

    public function test_create_set_not_exists()
    {
        $author = $this->create([
            'featured_tag' => [
                'id' => 'does_not_exist'
            ]
        ]);

        $this->assertFeaturedTag($author->id, []);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'featured_tag' => null
        ]);

        $this->assertFeaturedTag($author->id, []);

        $author = $this->create();

        $this->assertFeaturedTag($author->id, []);
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
                'featured_tag' => [
                    'name' => true
                ]
            ]
        ]);
    }

    protected function assertFeaturedTag(?string $id, ?array $tag)
    {
        $result = $this->get($id);

        // debug_dump(toArray($result));

        $data = $result['data'];

        if ($tag) {
            $this->assertEquals($tag[0], $data['featured_tag']['id']);
            $this->assertEquals($tag[1], $data['featured_tag']['name']);
        } else {
            $this->assertNull($data['featured_tag']);
        }
    }
}
