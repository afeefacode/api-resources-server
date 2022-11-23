<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;

class EloquentLinkManyRelationMorphToManyTest extends ApiResourcesEloquentTest
{
    public function test_get()
    {
        $author = $this->createAuthorWithTags(2);

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2']);
    }

    public function test_set_one()
    {
        $author = $this->createAuthorWithTags(2);

        Tag::factory()->create(['name' => 'tag3']);

        $this->save(
            id: $author->id,
            data: [
                'tags' => [
                    ['id' => '3']
                ]
            ]
        );

        $this->assertTags($author->id, ['3' => 'tag3']);
    }

    public function test_set_one_not_exists()
    {
        $author = $this->createAuthorWithTags(2);

        $this->save(
            id: $author->id,
            data: [
                'tags' => [
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, []);
    }

    public function test_set_many()
    {
        $author = $this->createAuthorWithTags(2);

        Tag::factory(2)->sequence(['name' => 'tag3'], ['name' => 'tag4'])->create();

        $this->save(
            id: $author->id,
            data: [
                'tags' => [
                    ['id' => '3'],
                    ['id' => '4'],
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, ['3' => 'tag3', '4' => 'tag4']);
    }

    public function test_set_empty()
    {
        $author = $this->createAuthorWithTags(2);

        $this->save(
            id: $author->id,
            data: [
                'tags' => []
            ]
        );

        $this->assertTags($author->id, []);
    }

    public function test_add_one()
    {
        $author = $this->createAuthorWithTags(2);

        Tag::factory()->create(['name' => 'tag3']);

        $this->save(
            id: $author->id,
            data: [
                'tags#add' => [
                    ['id' => '3']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2', '3' => 'tag3']);
    }

    public function test_add_one_not_exists()
    {
        $author = $this->createAuthorWithTags(2);

        $this->save(
            id: $author->id,
            data: [
                'tags#add' => [
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2']);
    }

    public function test_add_many()
    {
        $author = $this->createAuthorWithTags(2);

        Tag::factory(2)->sequence(['name' => 'tag3'], ['name' => 'tag4'])->create();

        $this->save(
            id: $author->id,
            data: [
                'tags#add' => [
                    ['id' => '3'],
                    ['id' => '4'],
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2', '3' => 'tag3', '4' => 'tag4']);
    }

    public function test_delete_one()
    {
        $author = $this->createAuthorWithTags(4);

        $this->save(
            id: $author->id,
            data: [
                'tags#delete' => [
                    ['id' => '3']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2', '4' => 'tag4']);
    }

    public function test_delete_one_not_exists()
    {
        $author = $this->createAuthorWithTags(3);

        $this->save(
            id: $author->id,
            data: [
                'tags#delete' => [
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2', '3' => 'tag3']);
    }

    public function test_delete_many()
    {
        $author = $this->createAuthorWithTags(5);

        $this->save(
            id: $author->id,
            data: [
                'tags#delete' => [
                    ['id' => '2'],
                    ['id' => '4'],
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertTags($author->id, ['1' => 'tag1', '3' => 'tag3', '5' => 'tag5']);
    }

    public function test_create_set_one()
    {
        Tag::factory()->create(['name' => 'tag1']);

        $author = $this->create([
            'tags' => [
                ['id' => '1']
            ]
        ]);

        $this->assertTags($author->id, ['1' => 'tag1']);
    }

    public function test_create_set_one_not_exists()
    {
        $author = $this->create([
            'tags' => [
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertTags($author->id, []);
    }

    public function test_create_set_many()
    {
        Tag::factory(2)->sequence(['name' => 'tag1'], ['name' => 'tag2'])->create();

        $author = $this->create([
            'tags' => [
                ['id' => '1'],
                ['id' => '2'],
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2']);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'tags' => []
        ]);

        $this->assertTags($author->id, []);

        $author = $this->create([]);

        $this->assertTags($author->id, []);
    }

    public function test_create_add_one()
    {
        Tag::factory()->create(['name' => 'tag1']);

        $author = $this->create([
            'tags#add' => [
                ['id' => '1']
            ]
        ]);

        $this->assertTags($author->id, ['1' => 'tag1']);
    }

    public function test_create_add_one_not_exists()
    {
        $author = $this->create([
            'tags#add' => [
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertTags($author->id, []);
    }

    public function test_create_add_many()
    {
        Tag::factory(2)->sequence(['name' => 'tag1'], ['name' => 'tag2'])->create();

        $author = $this->create([
            'tags#add' => [
                ['id' => '1'],
                ['id' => '2'],
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertTags($author->id, ['1' => 'tag1', '2' => 'tag2']);
    }

    public function test_create_delete()
    {
        Tag::factory()->create(['name' => 'tag1']);

        $author = $this->create([
            'tags#delete' => [
                ['id' => '1'],
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertTags($author->id, []);
    }

    protected function createAuthorWithTags($numTags): Author
    {
        $author = Author::factory()
            ->has(Tag::factory($numTags)->sequence(fn ($s) => ['name' => 'tag' . $s->index + 1]))
            ->create();

        $expectedTags = array_reduce(range(1, $numTags), function ($Tags, $index) {
            $Tags["{$index}"] = 'tag' . $index;
            return $Tags;
        }, []);

        $this->assertTags($author->id, $expectedTags);

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
                'count_tags' => true,
                'tags' => [
                    'name' => true
                ]
            ]
        ]);
    }

    protected function assertTags(string $id, array $names)
    {
        $result = $this->get($id);

        $data = $result['data'];
        $this->assertCount(count($names), $data['tags']);
        $this->assertEquals(count($names), $data['count_tags']);

        $index = 0;
        foreach ($names as $id => $name) {
            $this->assertEquals($id, $data['tags'][$index]['id']);
            $this->assertEquals($name, $data['tags'][$index]['name']);
            $index++;
        }
    }
}
