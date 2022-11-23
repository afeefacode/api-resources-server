<?php

namespace Afeefa\ApiResources\Tests\Eloquent\Blog;

use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Eloquent\Model;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use function Afeefa\ApiResources\Test\fake;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\BlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;

use PDOException;

class EloquentLinkOneRelationMorphToTest extends ApiResourcesEloquentTest
{
    public function test_set()
    {
        $comment = Comment::factory()->for(Author::factory(), 'owner')->create();
        $this->assertOwner($comment, ['1', Author::$type]);

        Author::factory()->create();

        $this->save(
            id: $comment->id,
            data: [
                'owner' => [
                    'id' => '2'
                ]
            ]
        );

        $this->assertOwner($comment, ['2', Author::$type]);
    }

    public function test_set_other_type()
    {
        $comment = Comment::factory()->for(Author::factory(), 'owner')->create();
        $this->assertOwner($comment, ['1', Author::$type]);

        Author::factory()->create();

        $this->save(
            id: $comment->id,
            data: [
                'owner' => [
                    'id' => '2'
                ]
            ]
        );

        $this->assertOwner($comment, ['2', Author::$type]);

        Article::factory()->forAuthor()->create();

        $this->save(
            id: $comment->id,
            data: [
                'owner' => [
                    'id' => '1',
                    'type' => Article::$type
                ]
            ]
        );

        $this->assertOwner($comment, ['1', Article::$type]);
    }

    public function test_set_not_exists()
    {
        $comment = Comment::factory()->for(Author::factory(), 'owner')->create();

        $this->save(
            id: $comment->id,
            data: [
                'owner' => [
                    'id' => 'does_not_exist'
                ]
            ]
        );

        $this->assertOwner($comment, ['1', Author::$type]);
    }

    public function test_set_empty()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Column 'owner_id' cannot be null/");

        $comment = Comment::factory()->for(Author::factory(), 'owner')->create();

        $this->save(
            id: $comment->id,
            data: [
                'owner' => null
            ]
        );

        $this->assertOwner($comment, []);
    }

    public function test_create_set()
    {
        Author::factory()->create();

        $comment = $this->create([
            'owner' => [
                'id' => '1',
                'type' => Author::$type
            ]
        ]);

        $this->assertOwner($comment, ['1',  Author::$type]);
    }

    public function test_create_set_other_type()
    {
        Article::factory()->forAuthor()->create();

        $comment = $this->create([
            'owner' => [
                'id' => '1',
                'type' => Article::$type
            ]
        ]);

        $this->assertOwner($comment, ['1',  Article::$type]);
    }

    public function test_create_set_only_id()
    {
        Author::factory()->create();

        $comment = $this->create([
            'owner' => [
                'id' => '1'
            ]
        ]);

        // takes first type = Author
        // CommentType::relation('owner', Type::link([AuthorType::class, ArticleType::class]));
        $this->assertOwner($comment, ['1',  Author::$type]);
    }

    public function test_create_set_not_exists()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Field 'owner_id' doesn't have a default value/");

        $this->create([
            'owner' => [
                'id' => 'does_not_exist'
            ]
        ]);
    }

    public function test_create_set_empty()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Column 'owner_id' cannot be null/");

        $this->create([
            'owner' => null
        ]);
    }

    public function test_create_set_empty2()
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches("/Field 'owner_id' doesn't have a default value/");

        $this->create();
    }

    public function test_get()
    {
        $comment = $this->createWithArticle();

        $this->assertOwner($comment, ['1',  Article::$type]);

        $result = $this->get('1', ['owner' => [
            'title' => true
        ]]);

        $data = $result['data'];
        $this->assertEquals('article1', $data['owner']['title']);
    }

    public function test_get_with_on_type()
    {
        $comment = $this->createWithArticle();

        // $this->assertOwner($comment, ['1',  Article::$type]);

        $result = $this->get('1', ['owner' => [
            '@Blog.Article' => [
                'title' => true
            ],
            '@Blog.Author' => [
                'name' => true
            ]
        ]]);

        $data = $result['data'];
        $this->assertEquals('article1', $data['owner']['title']);
        $this->assertNull($data['owner']['name']);
    }

    public function test_get_with_on_type2()
    {
        $comment = $this->createWithAuthor();

        $this->assertOwner($comment, ['1',  Author::$type]);

        $result = $this->get('1', ['owner' => [
            '@Blog.Article' => [
                'title' => true
            ],
            '@Blog.Author' => [
                'name' => true
            ]
        ]]);

        $data = $result['data'];
        $this->assertEquals('author1', $data['owner']['name']);
        $this->assertNull($data['owner']['title']);
    }

    protected function save(?string $id = null, array $data = []): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.CommentResource',
            'action' => 'save',
            'params' => [
                'id' => $id
            ],
            'data' => $data
        ]);
    }

    protected function create(array $data = []): Comment
    {
        ['data' => $comment] = $this->save(null, [
            'text' => fake()->text(),
            ...$data
        ]);
        return $comment;
    }

    protected function createWithAuthor(): Comment
    {
        Author::factory(['name' => 'author1'])->create();

        $comment = $this->create([
            'owner' => [
                'id' => '1',
                'type' => Author::$type
            ]
        ]);

        return $comment;
    }

    protected function createWithArticle(): Comment
    {
        Article::factory(['title' => 'article1'])->forAuthor()->create();

        $comment = $this->create([
            'owner' => [
                'id' => '1',
                'type' => Article::$type
            ]
        ]);

        return $comment;
    }

    protected function get(string $id, array $fields = []): array
    {
        return (new ApiResources())->requestFromInput(BlogApi::class, [
            'resource' => 'Blog.CommentResource',
            'action' => 'get',
            'params' => [
                'id' => $id
            ],
            'fields' => [
                'owner' => true,
                ...$fields
            ]
        ]);
    }

    protected function assertOwner(Comment $comment, mixed $owner)
    {
        $result = $this->get($comment->id);

        // debug_dump(toArray($result));

        $data = $result['data'];

        if ($owner) {
            if ($owner instanceof Model) {
                $owner = [$owner->id, $owner->type];
            }
            $this->assertEquals($owner[0], $data['owner']['id']);
            $this->assertEquals($owner[1], $data['owner']['type']);
        } else {
            $this->assertNull($data['owner']);
        }
    }
}
