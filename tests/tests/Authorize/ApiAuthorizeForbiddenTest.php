<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Api\PreviousAuthRule;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Link;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\LinkType;

/**
 * A mutating slot closed with false refuses the operation before any data is
 * written.
 *
 * The cases that prove "before" send data the database would reject: a closed
 * slot has to answer with the same NotFoundException as a denying rule, and
 * not with the database error of an insert that should never have run.
 */
class ApiAuthorizeForbiddenTest extends ApiResourcesAuthorizeTest
{
    // top level

    public function test_create_false_refuses_before_the_insert()
    {
        $this->expectException(NotFoundException::class);

        try {
            // email is NOT NULL - an insert would fail in the database
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)->create(false),
                $this->saveAuthor(null, ['name' => 'no email'])
            );
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    public function test_update_false_refuses_before_the_update()
    {
        $author = Author::factory()->create(['name' => 'one']);

        $this->expectException(NotFoundException::class);

        try {
            // name is NOT NULL - an update would fail in the database
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)->update(false),
                $this->saveAuthor($author->id, ['name' => null])
            );
        } finally {
            $this->assertEquals('one', Author::find($author->id)->name);
        }
    }

    public function test_delete_false_keeps_the_row()
    {
        $author = Author::factory()->create();

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)->delete(false),
                $this->saveAuthor($author->id, null)
            );
        } finally {
            $this->assertEquals(1, Author::count());
        }
    }

    public function test_a_closed_create_leaves_update_open()
    {
        $author = Author::factory()->create(['name' => 'one']);

        $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)->create(false),
            $this->saveAuthor($author->id, ['name' => 'two'])
        );

        $this->assertEquals('two', Author::find($author->id)->name);
    }

    public function test_write_false_closes_create_update_and_delete()
    {
        $author = Author::factory()->create(['name' => 'one']);
        $closeAll = fn (Api $api) => $api->authorize(AuthorType::class)->write(false);

        foreach ([
            $this->saveAuthor(null, ['name' => 'new', 'email' => 'new@one']),
            $this->saveAuthor($author->id, ['name' => 'two']),
            $this->saveAuthor($author->id, null)
        ] as $input) {
            try {
                $this->request($closeAll, $input);
                $this->fail('Expected a NotFoundException.');
            } catch (NotFoundException) {
            }
        }

        $this->assertEquals(1, Author::count());
        $this->assertEquals('one', Author::find($author->id)->name);
    }

    // nested

    public function test_nested_create_false_refuses_before_the_insert()
    {
        $author = Author::factory()->create(['name' => 'one']);

        $this->expectException(NotFoundException::class);

        try {
            // url is NOT NULL - an insert would fail in the database
            $this->request(
                fn (Api $api) => $api->authorize(LinkType::class)->create(false),
                $this->saveAuthor($author->id, ['name' => 'two', 'links' => [['url' => null]]])
            );
        } finally {
            $this->assertEquals(0, Link::count());
            $this->assertEquals('one', Author::find($author->id)->name);
        }
    }

    public function test_nested_update_false_refuses_before_the_update()
    {
        $author = Author::factory()->create();
        $link = Link::factory()->for($author)->create(['url' => 'link']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(LinkType::class)->update(false),
                $this->saveAuthor($author->id, ['links' => [['id' => $link->id, 'url' => null]]])
            );
        } finally {
            $this->assertEquals('link', Link::find($link->id)->url);
        }
    }

    public function test_nested_delete_false_keeps_the_row()
    {
        $author = Author::factory()->create();
        Link::factory()->for($author)->create();

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(LinkType::class)->delete(false),
                $this->saveAuthor($author->id, ['links' => []])
            );
        } finally {
            $this->assertEquals(1, Link::count());
        }
    }

    // registration order

    public function test_a_closure_registered_after_false_opens_the_slot_again()
    {
        $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)
                ->create(false)
                ->create(fn (EloquentAuthContext $c) => $c->query()),
            $this->saveAuthor(null, ['name' => 'one', 'email' => 'one@one'])
        );

        $this->assertEquals(1, Author::count());
    }

    public function test_handing_over_to_a_closed_slot_denies()
    {
        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->create(false)
                    ->create(fn (EloquentAuthContext $c, PreviousAuthRule $previous) => $previous->call($c)),
                $this->saveAuthor(null, ['name' => 'one', 'email' => 'one@one'])
            );
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    protected function saveAuthor(?string $id, ?array $data): array
    {
        return [
            'resource' => 'Blog.AuthorResource',
            'action' => 'save',
            'params' => ['id' => $id],
            'data' => $data,
            'fields' => ['name' => true]
        ];
    }
}
