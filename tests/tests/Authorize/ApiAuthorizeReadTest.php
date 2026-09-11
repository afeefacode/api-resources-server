<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Link;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ArticleType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\LinkType;

class ApiAuthorizeReadTest extends ApiResourcesAuthorizeTest
{
    // list top level

    public function test_list_returns_only_reachable_rows()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();

        ['data' => $data, 'meta' => $meta] = $this->request(
            fn (Api $api) => $api->authorize(
                AuthorType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            ),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertEquals(['ok one', 'ok two'], $this->values($data, 'name'));

        // the counters are taken from the same query, so they follow the rule
        $this->assertEquals(2, $meta['count_all']);
        $this->assertEquals(2, $meta['count_filter']);
        $this->assertEquals(2, $meta['count_search']);
    }

    public function test_list_without_registration_returns_everything()
    {
        Author::factory()->count(3)->create();

        ['data' => $data, 'meta' => $meta] = $this->request(null, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(3, $data);
        $this->assertEquals(3, $meta['count_all']);
    }

    public function test_list_with_empty_read_slot_returns_everything()
    {
        Author::factory()->count(3)->create();

        // registered, but only for delete - reading stays open
        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)
                ->delete(fn (EloquentAuthContext $c) => $c->deny()),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertCount(3, $data);
    }

    // get top level

    public function test_get_reachable_row_returns_model()
    {
        $authors = Author::factory()->count(2)->sequence(
            ['name' => 'ok one'],
            ['name' => 'blocked']
        )->create();

        ['data' => $data] = $this->request($this->onlyOkAuthors(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $authors[0]->id],
            'fields' => ['name' => true]
        ]);

        $this->assertEquals('ok one', $data['name']);
    }

    public function test_get_blocked_row_throws_not_found()
    {
        $authors = Author::factory()->count(2)->sequence(
            ['name' => 'ok one'],
            ['name' => 'blocked']
        )->create();

        $this->expectException(NotFoundException::class);

        $this->request($this->onlyOkAuthors(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $authors[1]->id],
            'fields' => ['name' => true]
        ]);
    }

    public function test_denying_read_blocks_get_without_any_where_clause()
    {
        $author = Author::factory()->create();

        $this->expectException(NotFoundException::class);

        $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->deny()),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'get',
                'params' => ['id' => $author->id],
                'fields' => ['name' => true]
            ]
        );
    }

    // nested read

    public function test_nested_read_applies_the_rule_of_the_related_type()
    {
        $author = Author::factory()->create();
        Link::factory()->for($author)->count(3)->sequence(
            ['url' => 'ok one'],
            ['url' => 'ok two'],
            ['url' => 'blocked']
        )->create();

        ['data' => $data] = $this->request($this->onlyOkLinks(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => [
                'name' => true,
                'links' => ['url' => true]
            ]
        ]);

        $this->assertEquals(['ok one', 'ok two'], $this->values($data[0]['links'], 'url'));
    }

    public function test_nested_read_without_registration_returns_everything()
    {
        $author = Author::factory()->create();
        Link::factory()->for($author)->count(3)->create();

        ['data' => $data] = $this->request(null, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => [
                'name' => true,
                'links' => ['url' => true]
            ]
        ]);

        $this->assertCount(3, $data[0]['links']);
    }

    public function test_nested_read_applies_the_rule_on_the_second_level()
    {
        $author = Author::factory()->create();
        Article::factory()->for($author)->create();
        Link::factory()->for($author)->count(3)->sequence(
            ['url' => 'ok one'],
            ['url' => 'ok two'],
            ['url' => 'blocked']
        )->create();

        ['data' => $data] = $this->request($this->onlyOkLinks(), [
            'resource' => 'Blog.ArticleResource',
            'action' => 'list',
            'fields' => [
                'title' => true,
                'author' => [
                    'name' => true,
                    'links' => ['url' => true]
                ]
            ]
        ]);

        $this->assertEquals(['ok one', 'ok two'], $this->values($data[0]['author']['links'], 'url'));
    }

    public function test_nested_read_of_a_polymorphic_relation_looks_up_every_target_type()
    {
        $okAuthor = Author::factory()->create(['name' => 'ok author']);
        $blockedAuthor = Author::factory()->create(['name' => 'blocked author']);
        $article = Article::factory()->for($okAuthor)->create(['title' => 'an article']);

        $this->comment('on ok author', $okAuthor);
        $this->comment('on blocked author', $blockedAuthor);
        $this->comment('on article', $article);

        ['data' => $data] = $this->request($this->onlyOkAuthors(), [
            'resource' => 'Blog.CommentResource',
            'action' => 'list',
            'fields' => [
                'text' => true,
                'owner' => ['name' => true, 'title' => true]
            ]
        ]);

        $owners = [];
        foreach ($data as $comment) {
            $owners[$comment['text']] = $comment['owner']['name'] ?? $comment['owner']['title'] ?? null;
        }

        // the author rule filters the author owners, the unregistered article
        // type stays open
        $this->assertEquals('ok author', $owners['on ok author']);
        $this->assertNull($owners['on blocked author']);
        $this->assertEquals('an article', $owners['on article']);
    }

    // relation counts

    public function test_relation_count_matches_the_nested_read_next_to_it()
    {
        $author = Author::factory()->create();
        Link::factory()->for($author)->count(3)->sequence(
            ['url' => 'ok one'],
            ['url' => 'ok two'],
            ['url' => 'blocked']
        )->create();

        ['data' => $data] = $this->request($this->onlyOkLinks(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => [
                'count_links' => true,
                'links' => ['url' => true]
            ]
        ]);

        $this->assertEquals(2, $data[0]['count_links']);
        $this->assertCount(2, $data[0]['links']);
    }

    public function test_relation_count_alone_is_filtered_as_well()
    {
        $author = Author::factory()->create();
        Article::factory()->for($author)->count(3)->sequence(
            ['title' => 'ok one'],
            ['title' => 'ok two'],
            ['title' => 'blocked']
        )->create();

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(
                ArticleType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('title', 'like', 'ok%')
            ),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['count_articles' => true]
            ]
        );

        $this->assertEquals(2, $data[0]['count_articles']);
    }

    public function test_relation_count_without_registration_counts_everything()
    {
        $author = Author::factory()->create();
        Article::factory()->for($author)->count(3)->create();

        ['data' => $data] = $this->request(null, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['count_articles' => true]
        ]);

        $this->assertEquals(3, $data[0]['count_articles']);
    }

    public function test_nested_relation_count_is_filtered()
    {
        $author = Author::factory()->create();
        Article::factory()->for($author)->count(3)->sequence(
            ['title' => 'ok one'],
            ['title' => 'ok two'],
            ['title' => 'blocked']
        )->create();

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(
                ArticleType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('title', 'like', 'ok%')
            ),
            [
                'resource' => 'Blog.ArticleResource',
                'action' => 'list',
                'fields' => [
                    'title' => true,
                    'author' => ['count_articles' => true]
                ]
            ]
        );

        $this->assertEquals(2, $data[0]['author']['count_articles']);
    }

    /**
     * Field values of a result set, sorted - the order of a list is not what
     * these tests are about.
     */
    protected function values($items, string $field): array
    {
        $values = [];
        foreach ($items as $item) {
            $values[] = $item[$field];
        }
        sort($values);
        return $values;
    }

    protected function onlyOkAuthors(): callable
    {
        return fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        );
    }

    protected function onlyOkLinks(): callable
    {
        return fn (Api $api) => $api->authorize(
            LinkType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('url', 'like', 'ok%')
        );
    }

    protected function comment(string $text, $owner): Comment
    {
        $comment = new Comment();
        $comment->text = $text;
        $comment->owner_id = $owner->id;
        $comment->owner_type = $owner::$type;
        $comment->save();
        return $comment;
    }
}
