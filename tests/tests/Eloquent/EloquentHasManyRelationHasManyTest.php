<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Link;

class EloquentHasManyRelationHasManyTest extends ApiResourcesEloquentTest
{
    public function test_set_one()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['url' => 'link3']
                ]
            ]
        );

        $this->assertLinks($author->id, ['3' => 'link3']);
    }

    public function test_set_one_update()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['id' => '1', 'url' => 'link1_update']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1_update']);
    }

    public function test_set_one_update_of_other()
    {
        $author = $this->createAuthorWithLinks(2);

        Link::factory()->forAuthor()->create(['url' => 'link3']);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['id' => '3', 'url' => 'link3_update']
                ]
            ]
        );

        $this->assertLinks($author->id, ['4' => 'link3_update']);

        $this->assertEquals('link3', Link::find('3')->url);
    }

    public function test_set_one_update_not_exists()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['id' => 'does_not_exist', 'url' => 'link3']
                ]
            ]
        );

        $this->assertLinks($author->id, ['3' => 'link3']);
    }

    public function test_set_many()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['url' => 'link3'],
                    ['url' => 'link4']
                ]
            ]
        );

        $this->assertLinks($author->id, ['3' => 'link3', '4' => 'link4']);
    }

    public function test_set_many_update()
    {
        $author = $this->createAuthorWithLinks(2);

        Link::factory()->forAuthor()->create(['url' => 'link3']);

        $this->save(
            id: $author->id,
            data: [
                'links' => [
                    ['id' => '1', 'url' => 'link1_update'],
                    ['id' => '3', 'url' => 'link3_update'],
                    ['url' => 'link5'],
                    ['id' => 'does_not_exist', 'url' => 'link6'],
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1_update', '4' => 'link3_update', '5' => 'link5', '6' => 'link6']);
    }

    public function test_set_empty()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links' => []
            ]
        );

        $this->assertLinks($author->id, []);
    }

    public function test_add_one()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['url' => 'link3']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '3' => 'link3']);
    }

    public function test_add_one_update()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['id' => '1', 'url' => 'link1_update']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1_update', '2' => 'link2']);
    }

    public function test_add_one_update_of_other()
    {
        $author = $this->createAuthorWithLinks(2);

        Link::factory()->forAuthor()->create(['url' => 'link3']);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['id' => '3', 'url' => 'link3_update']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '4' => 'link3_update']);

        $this->assertEquals('link3', Link::find('3')->url);
    }

    public function test_add_one_update_not_exists()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['id' => 'does_not_exist', 'url' => 'link3']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '3' => 'link3']);
    }

    public function test_add_empty()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => []
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2']);
    }

    public function test_add_many()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['url' => 'link3'],
                    ['url' => 'link4']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '3' => 'link3', '4' => 'link4']);
    }

    public function test_add_many_update()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['id' => '1', 'url' => 'link1_update'],
                    ['url' => 'link3'],
                    ['id' => 'does_not_exist', 'url' => 'link4'],
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1_update', '2' => 'link2', '3' => 'link3', '4' => 'link4']);
    }

    public function test_add_many_update_of_other()
    {
        $author = $this->createAuthorWithLinks(2);

        Link::factory()->forAuthor()->create(['url' => 'link3']);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['id' => '3', 'url' => 'link3_update'],
                    ['url' => 'link5']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '4' => 'link3_update', '5' => 'link5']);

        $this->assertEquals('link3', Link::find('3')->url);
    }

    public function test_delete_one()
    {
        $author = $this->createAuthorWithLinks(3);

        $this->save(
            id: $author->id,
            data: [
                'links#delete' => [
                    ['id' => '2']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '3' => 'link3']);
    }

    public function test_delete_one_not_exists()
    {
        $author = $this->createAuthorWithLinks(3);

        $this->save(
            id: $author->id,
            data: [
                'links#delete' => [
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '3' => 'link3']);
    }

    public function test_delete_many()
    {
        $author = $this->createAuthorWithLinks(5);

        $this->save(
            id: $author->id,
            data: [
                'links#delete' => [
                    ['id' => '2'],
                    ['id' => '3'],
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '4' => 'link4', '5' => 'link5']);
    }

    public function test_add_delete()
    {
        $author = $this->createAuthorWithLinks(2);

        $this->save(
            id: $author->id,
            data: [
                'links#delete' => [
                    ['id' => '2']
                ],
                'links#add' => [
                    ['url' => 'link3']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2', '3' => 'link3']);

        $this->save(
            id: $author->id,
            data: [
                'links#add' => [
                    ['url' => 'link4']
                ],
                'links#delete' => [
                    ['id' => '2']
                ]
            ]
        );

        $this->assertLinks($author->id, ['1' => 'link1', '3' => 'link3']);
    }

    public function test_create_set_one()
    {
        $author = $this->create([
            'links' => [
                ['url' => 'link1']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link1']);
    }

    public function test_create_set_one_update_of_other()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links' => [
                ['id' => '1', 'url' => 'link1_update']
            ]
        ]);

        $this->assertLinks($author->id, ['2' => 'link1_update']);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    public function test_create_set_one_update_not_exists()
    {
        $author = $this->create([
            'links' => [
                ['id' => 'does_not_exist', 'url' => 'link3']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link3']);
    }

    public function test_create_set_many()
    {
        $author = $this->create([
            'links' => [
                ['url' => 'link1'],
                ['url' => 'link2']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2']);
    }

    public function test_create_set_many_update_of_other()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links' => [
                ['id' => '1', 'url' => 'link1_update'],
                ['url' => 'link3'],
                ['id' => 'does_not_exist', 'url' => 'link4'],
            ]
        ]);

        $this->assertLinks($author->id, [
            '2' => 'link1_update',
            '3' => 'link3',
            '4' => 'link4'
        ]);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'links' => []
        ]);

        $this->assertLinks($author->id, []);

        $author = $this->create();

        $this->assertLinks($author->id, []);
    }

    public function test_create_add_one()
    {
        $author = $this->create([
            'links#add' => [
                ['url' => 'link1']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link1']);
    }

    public function test_create_add_one_update_of_other()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links#add' => [
                ['id' => '1', 'url' => 'link1_update']
            ]
        ]);

        $this->assertLinks($author->id, ['2' => 'link1_update']);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    public function test_create_add_one_update_not_exists()
    {
        $author = $this->create([
            'links#add' => [
                ['id' => 'does_not_exist', 'url' => 'link3']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link3']);
    }

    public function test_create_add_empty()
    {
        $author = $this->create([
            'links#add' => []
        ]);

        $this->assertLinks($author->id, []);
    }

    public function test_create_add_many()
    {
        $author = $this->create([
            'links#add' => [
                ['url' => 'link1'],
                ['url' => 'link2']
            ]
        ]);

        $this->assertLinks($author->id, ['1' => 'link1', '2' => 'link2']);
    }

    public function test_create_add_many_update_of_other()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links#add' => [
                ['id' => '1', 'url' => 'link1_update'],
                ['url' => 'link3'],
                ['id' => 'does_not_exist', 'url' => 'link4'],
            ]
        ]);

        $this->assertLinks($author->id, [
            '2' => 'link1_update',
            '3' => 'link3',
            '4' => 'link4'
        ]);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    public function test_create_delete_one_of_other()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links#delete' => [
                ['id' => '1']
            ]
        ]);

        $this->assertLinks($author->id, []);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    public function test_create_delete_one_not_exists()
    {
        $author = $this->create([
            'links#delete' => [
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertLinks($author->id, []);
    }

    public function test_create_add_delete()
    {
        Link::factory()->forAuthor()->create(['url' => 'link1']);

        $author = $this->create([
            'links#add' => [
                ['url' => 'link2']
            ],
            'links#delete' => [
                ['id' => '1']
            ]
        ]);

        $this->assertLinks($author->id, []);

        $this->assertEquals('link1', Link::find('1')->url);

        $author = $this->create([
            'links#delete' => [
                ['id' => '1']
            ],
            'links#add' => [
                ['url' => 'link2']
            ]
        ]);

        $this->assertLinks($author->id, ['2' => 'link2']);

        $this->assertEquals('link1', Link::find('1')->url);
    }

    protected function createAuthorWithLinks($numLinks): Author
    {
        $author = Author::factory()
            ->has(Link::factory($numLinks)->sequence(fn ($s) => ['url' => 'link' . $s->index + 1]))
            ->create();

        $expectedLinks = array_reduce(range(1, $numLinks), function ($links, $index) {
            $links["{$index}"] = 'link' . $index;
            return $links;
        }, []);

        $this->assertLinks($author->id, $expectedLinks);

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
                'count_links' => true,
                'links' => [
                    'url' => true
                ]
            ]
        ]);
    }

    protected function assertLinks(string $id, array $names)
    {
        $result = $this->get($id);

        $data = $result['data'];
        $this->assertCount(count($names), $data['links']);
        $this->assertEquals(count($names), $data['count_links']);

        $index = 0;
        foreach ($names as $id => $name) {
            $this->assertEquals($id, $data['links'][$index]['id']);
            $this->assertEquals($name, $data['links'][$index]['url']);
            $index++;
        }
    }
}
