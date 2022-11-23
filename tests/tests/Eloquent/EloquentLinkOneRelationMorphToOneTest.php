<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;

use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;

class EloquentLinkOneRelationMorphToOneTest extends ApiResourcesEloquentTest
{
    public function test_set()
    {
        $author = Author::factory()->create();

        Tag::factory()->create(['name' => 'tag1']);

        $this->save(
            id: $author->id,
            data: [
                'first_tag' => [
                    'id' => '1'
                ]
            ]
        );

        $this->assertFirstTag($author->id, ['1', 'tag1']);
    }

    public function test_set_not_exists()
    {
        $author = Author::factory()->create();

        $this->save(
            id: $author->id,
            data: [
                'first_tag' => [
                    'id' => 'does_not_exist'
                ]
            ]
        );

        $this->assertFirstTag($author->id, []);
    }

    public function test_set_empty()
    {
        $author = Author::factory()
            ->has(Tag::factory(['name' => 'tag1']), 'first_tag')
            ->create();

        $this->assertFirstTag($author->id, ['1', 'tag1']);

        $this->save(
            id: $author->id,
            data: [
                'first_tag' => null
            ]
        );

        $this->assertFirstTag($author->id, []);
    }

    public function test_create_set()
    {
        Tag::factory()->create(['name' => 'tag1']);

        $author = $this->create([
            'first_tag' => [
                'id' => '1'
            ]
        ]);

        $this->assertFirstTag($author->id, ['1', 'tag1']);
    }

    public function test_create_set_not_exists()
    {
        $author = $this->create([
            'first_tag' => [
                'id' => 'does_not_exist'
            ]
        ]);

        $this->assertFirstTag($author->id, []);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'first_tag' => null
        ]);

        $this->assertFirstTag($author->id, []);

        $author = $this->create();

        $this->assertFirstTag($author->id, []);
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
                'first_tag' => [
                    'name' => true
                ]
            ]
        ]);
    }

    protected function assertFirstTag(?string $id, ?array $tag)
    {
        $result = $this->get($id);

        // debug_dump(toArray($result));

        $data = $result['data'];

        if ($tag) {
            $this->assertEquals($tag[0], $data['first_tag']['id']);
            $this->assertEquals($tag[1], $data['first_tag']['name']);
        } else {
            $this->assertNull($data['first_tag']);
        }
    }
}
