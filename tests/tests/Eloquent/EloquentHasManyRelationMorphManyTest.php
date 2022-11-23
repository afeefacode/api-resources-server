<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;

class EloquentHasManyRelationMorphManyTest extends ApiResourcesEloquentTest
{
    public function test_set_one()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['text' => 'comment3']
                ]
            ]
        );

        $this->assertComments($author->id, ['3' => 'comment3']);
    }

    public function test_set_one_update()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['id' => '1', 'text' => 'comment1_update']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1_update']);
    }

    public function test_set_one_update_of_other()
    {
        $author = $this->createAuthorWithComments(2);

        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment3']);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['id' => '3', 'text' => 'comment3_update']
                ]
            ]
        );

        $this->assertComments($author->id, ['4' => 'comment3_update']);

        $this->assertEquals('comment3', Comment::find('3')->text);
    }

    public function test_set_one_update_not_exists()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['id' => 'does_not_exist', 'text' => 'comment3']
                ]
            ]
        );

        $this->assertComments($author->id, ['3' => 'comment3']);
    }

    public function test_set_many()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['text' => 'comment3'],
                    ['text' => 'comment4']
                ]
            ]
        );

        $this->assertComments($author->id, ['3' => 'comment3', '4' => 'comment4']);
    }

    public function test_set_many_update()
    {
        $author = $this->createAuthorWithComments(2);

        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment3']);

        $this->save(
            id: $author->id,
            data: [
                'comments' => [
                    ['id' => '1', 'text' => 'comment1_update'],
                    ['id' => '3', 'text' => 'comment3_update'],
                    ['text' => 'comment5'],
                    ['id' => 'does_not_exist', 'text' => 'comment6'],
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1_update', '4' => 'comment3_update', '5' => 'comment5', '6' => 'comment6']);
    }

    public function test_set_empty()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments' => []
            ]
        );

        $this->assertComments($author->id, []);
    }

    public function test_add_one()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['text' => 'comment3']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '3' => 'comment3']);
    }

    public function test_add_one_update()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['id' => '1', 'text' => 'comment1_update']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1_update', '2' => 'comment2']);
    }

    public function test_add_one_update_of_other()
    {
        $author = $this->createAuthorWithComments(2);

        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment3']);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['id' => '3', 'text' => 'comment3_update']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '4' => 'comment3_update']);

        $this->assertEquals('comment3', Comment::find('3')->text);
    }

    public function test_add_one_update_not_exists()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['id' => 'does_not_exist', 'text' => 'comment3']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '3' => 'comment3']);
    }

    public function test_add_empty()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => []
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2']);
    }

    public function test_add_many()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['text' => 'comment3'],
                    ['text' => 'comment4']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '3' => 'comment3', '4' => 'comment4']);
    }

    public function test_add_many_update()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['id' => '1', 'text' => 'comment1_update'],
                    ['text' => 'comment3'],
                    ['id' => 'does_not_exist', 'text' => 'comment4'],
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1_update', '2' => 'comment2', '3' => 'comment3', '4' => 'comment4']);
    }

    public function test_add_many_update_of_other()
    {
        $author = $this->createAuthorWithComments(2);

        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment3']);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['id' => '3', 'text' => 'comment3_update'],
                    ['text' => 'comment5']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '4' => 'comment3_update', '5' => 'comment5']);

        $this->assertEquals('comment3', Comment::find('3')->text);
    }

    public function test_delete_one()
    {
        $author = $this->createAuthorWithComments(3);

        $this->save(
            id: $author->id,
            data: [
                'comments#delete' => [
                    ['id' => '2']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '3' => 'comment3']);
    }

    public function test_delete_one_not_exists()
    {
        $author = $this->createAuthorWithComments(3);

        $this->save(
            id: $author->id,
            data: [
                'comments#delete' => [
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '3' => 'comment3']);
    }

    public function test_delete_many()
    {
        $author = $this->createAuthorWithComments(5);

        $this->save(
            id: $author->id,
            data: [
                'comments#delete' => [
                    ['id' => '2'],
                    ['id' => '3'],
                    ['id' => 'does_not_exist']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '4' => 'comment4', '5' => 'comment5']);
    }

    public function test_add_delete()
    {
        $author = $this->createAuthorWithComments(2);

        $this->save(
            id: $author->id,
            data: [
                'comments#delete' => [
                    ['id' => '2']
                ],
                'comments#add' => [
                    ['text' => 'comment3']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2', '3' => 'comment3']);

        $this->save(
            id: $author->id,
            data: [
                'comments#add' => [
                    ['text' => 'comment4']
                ],
                'comments#delete' => [
                    ['id' => '2']
                ]
            ]
        );

        $this->assertComments($author->id, ['1' => 'comment1', '3' => 'comment3']);
    }

    public function test_create_set_one()
    {
        $author = $this->create([
            'comments' => [
                ['text' => 'comment1']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment1']);
    }

    public function test_create_set_one_update_of_other()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments' => [
                ['id' => '1', 'text' => 'comment1_update']
            ]
        ]);

        $this->assertComments($author->id, ['2' => 'comment1_update']);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    public function test_create_set_one_update_not_exists()
    {
        $author = $this->create([
            'comments' => [
                ['id' => 'does_not_exist', 'text' => 'comment3']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment3']);
    }

    public function test_create_set_many()
    {
        $author = $this->create([
            'comments' => [
                ['text' => 'comment1'],
                ['text' => 'comment2']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2']);
    }

    public function test_create_set_many_update_of_other()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments' => [
                ['id' => '1', 'text' => 'comment1_update'],
                ['text' => 'comment3'],
                ['id' => 'does_not_exist', 'text' => 'comment4'],
            ]
        ]);

        $this->assertComments($author->id, [
            '2' => 'comment1_update',
            '3' => 'comment3',
            '4' => 'comment4'
        ]);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    public function test_create_set_empty()
    {
        $author = $this->create([
            'comments' => []
        ]);

        $this->assertComments($author->id, []);

        $author = $this->create();

        $this->assertComments($author->id, []);
    }

    public function test_create_add_one()
    {
        $author = $this->create([
            'comments#add' => [
                ['text' => 'comment1']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment1']);
    }

    public function test_create_add_one_update_of_other()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments#add' => [
                ['id' => '1', 'text' => 'comment1_update']
            ]
        ]);

        $this->assertComments($author->id, ['2' => 'comment1_update']);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    public function test_create_add_one_update_not_exists()
    {
        $author = $this->create([
            'comments#add' => [
                ['id' => 'does_not_exist', 'text' => 'comment3']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment3']);
    }

    public function test_create_add_empty()
    {
        $author = $this->create([
            'comments#add' => []
        ]);

        $this->assertComments($author->id, []);
    }

    public function test_create_add_many()
    {
        $author = $this->create([
            'comments#add' => [
                ['text' => 'comment1'],
                ['text' => 'comment2']
            ]
        ]);

        $this->assertComments($author->id, ['1' => 'comment1', '2' => 'comment2']);
    }

    public function test_create_add_many_update_of_other()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments#add' => [
                ['id' => '1', 'text' => 'comment1_update'],
                ['text' => 'comment3'],
                ['id' => 'does_not_exist', 'text' => 'comment4'],
            ]
        ]);

        $this->assertComments($author->id, [
            '2' => 'comment1_update',
            '3' => 'comment3',
            '4' => 'comment4'
        ]);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    public function test_create_delete_one_of_other()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments#delete' => [
                ['id' => '1']
            ]
        ]);

        $this->assertComments($author->id, []);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    public function test_create_delete_one_not_exists()
    {
        $author = $this->create([
            'comments#delete' => [
                ['id' => 'does_not_exist']
            ]
        ]);

        $this->assertComments($author->id, []);
    }

    public function test_create_add_delete()
    {
        Comment::factory()->for(Author::factory(), 'owner')->create(['text' => 'comment1']);

        $author = $this->create([
            'comments#add' => [
                ['text' => 'comment2']
            ],
            'comments#delete' => [
                ['id' => '1']
            ]
        ]);

        $this->assertComments($author->id, []);

        $this->assertEquals('comment1', Comment::find('1')->text);

        $author = $this->create([
            'comments#delete' => [
                ['id' => '1']
            ],
            'comments#add' => [
                ['text' => 'comment2']
            ]
        ]);

        $this->assertComments($author->id, ['2' => 'comment2']);

        $this->assertEquals('comment1', Comment::find('1')->text);
    }

    protected function createAuthorWithComments($numComments): Author
    {
        $author = Author::factory()
            ->has(Comment::factory($numComments)->sequence(fn ($s) => ['text' => 'comment' . $s->index + 1]))
            ->create();

        $expectedComments = array_reduce(range(1, $numComments), function ($comments, $index) {
            $comments["{$index}"] = 'comment' . $index;
            return $comments;
        }, []);

        $this->assertComments($author->id, $expectedComments);

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
                'count_comments' => true,
                'comments' => [
                    'text' => true
                ]
            ]
        ]);
    }

    protected function assertComments(string $id, array $names)
    {
        $result = $this->get($id);

        $data = $result['data'];
        $this->assertCount(count($names), $data['comments']);
        $this->assertEquals(count($names), $data['count_comments']);

        $index = 0;
        foreach ($names as $id => $name) {
            $this->assertEquals($id, $data['comments'][$index]['id']);
            $this->assertEquals($name, $data['comments'][$index]['text']);
            $index++;
        }
    }
}
