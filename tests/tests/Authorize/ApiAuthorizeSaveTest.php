<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Link;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\LinkType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\TagType;

class ApiAuthorizeSaveTest extends ApiResourcesAuthorizeTest
{
    // add top level

    public function test_add_reachable_row_creates_it()
    {
        ['data' => $data] = $this->request($this->onlyOkAuthors(), $this->saveAuthor(null, [
            'name' => 'ok one',
            'email' => 'ok@one'
        ]));

        $this->assertEquals('ok one', $data['name']);
        $this->assertEquals(1, Author::count());
    }

    public function test_add_row_outside_the_create_rule_rolls_back()
    {
        $this->expectException(NotFoundException::class);

        try {
            $this->request($this->onlyOkAuthors(), $this->saveAuthor(null, [
                'name' => 'blocked one',
                'email' => 'blocked@one'
            ]));
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    public function test_add_is_blocked_by_the_create_slot_alone()
    {
        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->create(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor(null, ['name' => 'ok one', 'email' => 'ok@one'])
            );
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    // update top level

    public function test_update_within_the_rule_is_saved()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->request($this->onlyOkAuthors(), $this->saveAuthor($author->id, ['name' => 'ok two']));

        $this->assertEquals('ok two', Author::find($author->id)->name);
    }

    public function test_update_out_of_the_rule_rolls_back()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request($this->onlyOkAuthors(), $this->saveAuthor($author->id, ['name' => 'blocked']));
        } finally {
            $this->assertEquals('ok one', Author::find($author->id)->name);
        }
    }

    public function test_update_of_an_unreadable_row_fails_before_the_save()
    {
        $author = Author::factory()->create(['name' => 'blocked one']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                // reading is blocked, writing would be allowed - the loader in
                // front of the save decides
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->read(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor($author->id, ['name' => 'ok two'])
            );
        } finally {
            $this->assertEquals('blocked one', Author::find($author->id)->name);
        }
    }

    // delete top level

    public function test_delete_within_the_rule_removes_the_row()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->request($this->onlyOkAuthors(), $this->saveAuthor($author->id, null));

        $this->assertEquals(0, Author::count());
    }

    public function test_delete_blocked_by_the_delete_slot_keeps_the_row()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->delete(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor($author->id, null)
            );
        } finally {
            $this->assertEquals(1, Author::count());
        }
    }

    // nested save

    public function test_nested_create_within_the_rule_is_saved()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->request($this->onlyOkLinks(), $this->saveAuthor($author->id, [
            'links' => [['url' => 'ok link']]
        ]));

        $this->assertEquals(['ok link'], Link::pluck('url')->all());
    }

    public function test_nested_create_outside_the_rule_rolls_the_owner_save_back()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request($this->onlyOkLinks(), $this->saveAuthor($author->id, [
                'name' => 'ok two',
                'links' => [['url' => 'blocked link']]
            ]));
        } finally {
            $this->assertEquals(0, Link::count());
            $this->assertEquals('ok one', Author::find($author->id)->name);
        }
    }

    public function test_nested_update_within_the_rule_is_saved()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $link = Link::factory()->for($author)->create(['url' => 'ok link']);

        $this->request($this->onlyOkLinks(), $this->saveAuthor($author->id, [
            'links' => [['id' => $link->id, 'url' => 'ok link two']]
        ]));

        $this->assertEquals('ok link two', Link::find($link->id)->url);
    }

    public function test_nested_update_out_of_the_rule_rolls_the_owner_save_back()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $link = Link::factory()->for($author)->create(['url' => 'ok link']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request($this->onlyOkLinks(), $this->saveAuthor($author->id, [
                'name' => 'ok two',
                'links' => [['id' => $link->id, 'url' => 'blocked link']]
            ]));
        } finally {
            $this->assertEquals('ok link', Link::find($link->id)->url);
            $this->assertEquals('ok one', Author::find($author->id)->name);
        }
    }

    public function test_nested_delete_is_blocked_by_the_delete_slot_of_the_target_type()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $link = Link::factory()->for($author)->create(['url' => 'ok link']);

        $this->expectException(NotFoundException::class);

        try {
            // dropping the link from the list deletes the row
            $this->request(
                fn (Api $api) => $api->authorize(LinkType::class)
                    ->delete(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor($author->id, ['links' => []])
            );
        } finally {
            $this->assertEquals(1, Link::count());
        }
    }

    public function test_nested_delete_within_the_rule_removes_the_row()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        Link::factory()->for($author)->create(['url' => 'ok link']);

        $this->request(
            fn (Api $api) => $api->authorize(LinkType::class)
                ->delete(fn (EloquentAuthContext $c) => $c->query()),
            $this->saveAuthor($author->id, ['links' => []])
        );

        $this->assertEquals(0, Link::count());
    }

    // detach

    public function test_detach_is_blocked_by_the_update_slot_of_the_owner_type()
    {
        [$author, $tags] = $this->authorWithTags();

        $this->expectException(NotFoundException::class);

        try {
            // the target type may not be deleted, but detaching does not delete
            // it - the owner decides
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->update(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor($author->id, ['tags' => [['id' => $tags[0]->id]]])
            );
        } finally {
            $this->assertEquals(2, Author::find($author->id)->tags()->count());
        }
    }

    public function test_detach_is_not_blocked_by_the_delete_slot_of_the_target_type()
    {
        [$author, $tags] = $this->authorWithTags();

        $this->request(
            fn (Api $api) => $api->authorize(TagType::class)
                ->delete(fn (EloquentAuthContext $c) => $c->deny()),
            $this->saveAuthor($author->id, ['tags' => [['id' => $tags[0]->id]]])
        );

        $this->assertEquals(1, Author::find($author->id)->tags()->count());
        $this->assertEquals(2, Tag::count()); // the row itself stays
    }

    // link

    public function test_link_to_an_unreachable_id_throws_and_saves_nothing()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $tag = Tag::factory()->create(['name' => 'blocked tag']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(
                    TagType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
                ),
                $this->saveAuthor($author->id, [
                    'name' => 'ok two',
                    'featured_tag' => ['id' => $tag->id]
                ])
            );
        } finally {
            $this->assertNull(Author::find($author->id)->featured_tag_id);
            $this->assertEquals('ok one', Author::find($author->id)->name);
        }
    }

    public function test_link_to_a_reachable_id_is_saved()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $tag = Tag::factory()->create(['name' => 'ok tag']);

        $this->request(
            fn (Api $api) => $api->authorize(
                TagType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            ),
            $this->saveAuthor($author->id, ['featured_tag' => ['id' => $tag->id]])
        );

        $this->assertEquals($tag->id, Author::find($author->id)->featured_tag_id);
    }

    public function test_an_unreadable_existing_link_is_not_unlinked()
    {
        [$author, $tags] = $this->authorWithTags();

        // tag two is out of reach, so it does not show up as an existing link
        // and a save without it does not drop it
        $this->request(
            fn (Api $api) => $api->authorize(
                TagType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            ),
            $this->saveAuthor($author->id, ['tags' => [['id' => $tags[0]->id]]])
        );

        $this->assertEquals([$tags[0]->id, $tags[1]->id], Author::find($author->id)->tags()->pluck('tags.id')->all());
    }

    public function test_an_unreadable_belongs_to_link_is_replaced_anyway()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $blockedTag = Tag::factory()->create(['name' => 'blocked tag']);
        $okTag = Tag::factory()->create(['name' => 'ok tag']);
        $author->featured_tag_id = $blockedTag->id;
        $author->save();

        // Counterpart to the case above: here the foreign key sits in the owner
        // row itself, so replacing it is a change to the owner and gets written
        // over regardless of whether the previous target was visible.
        $this->request($this->onlyOkTags(), $this->saveAuthor($author->id, [
            'featured_tag' => ['id' => $okTag->id]
        ]));

        $this->assertEquals($okTag->id, Author::find($author->id)->featured_tag_id);
    }

    public function test_replacing_a_belongs_to_link_falls_under_the_update_slot_of_the_owner()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $blockedTag = Tag::factory()->create(['name' => 'blocked tag']);
        $okTag = Tag::factory()->create(['name' => 'ok tag']);
        $author->featured_tag_id = $blockedTag->id;
        $author->save();

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->update(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor($author->id, ['featured_tag' => ['id' => $okTag->id]])
            );
        } finally {
            $this->assertEquals($blockedTag->id, Author::find($author->id)->featured_tag_id);
        }
    }

    // documented misconfiguration

    public function test_create_allowed_and_read_blocked_writes_the_row_and_answers_not_found()
    {
        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorType::class)
                    ->read(fn (EloquentAuthContext $c) => $c->deny()),
                $this->saveAuthor(null, ['name' => 'ok one', 'email' => 'ok@one'])
            );
        } finally {
            // the get request that follows the save runs outside the
            // transaction, so the row stays - who may write a type has to be
            // allowed to read it
            $this->assertEquals(1, Author::count());
        }
    }

    // write umbrella

    public function test_write_umbrella_blocks_update_create_and_delete()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $configure = fn (Api $api) => $api->authorize(AuthorType::class)
            ->write(fn (EloquentAuthContext $c) => $c->deny());

        foreach ([
            [null, ['name' => 'ok two', 'email' => 'ok@two']],
            [$author->id, ['name' => 'ok two']],
            [$author->id, null]
        ] as [$id, $data]) {
            $thrown = false;
            try {
                $this->request($configure, $this->saveAuthor($id, $data));
            } catch (NotFoundException) {
                $thrown = true;
            }
            $this->assertTrue($thrown);
        }

        $this->assertEquals(1, Author::count());
        $this->assertEquals('ok one', Author::find($author->id)->name);
    }

    public function test_write_umbrella_leaves_reading_open()
    {
        Author::factory()->create(['name' => 'ok one']);

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)
                ->write(fn (EloquentAuthContext $c) => $c->deny()),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertCount(1, $data);
    }

    public function test_a_per_op_call_after_write_narrows_only_that_op()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        // write allows everything, delete alone is closed
        $configure = fn (Api $api) => $api->authorize(AuthorType::class)
            ->write(fn (EloquentAuthContext $c) => $c->query())
            ->delete(fn (EloquentAuthContext $c) => $c->deny());

        $this->request($configure, $this->saveAuthor($author->id, ['name' => 'ok two']));
        $this->assertEquals('ok two', Author::find($author->id)->name);

        $this->expectException(NotFoundException::class);

        try {
            $this->request($configure, $this->saveAuthor($author->id, null));
        } finally {
            $this->assertEquals(1, Author::count());
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

    protected function onlyOkAuthors(): callable
    {
        return fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        );
    }

    protected function onlyOkTags(): callable
    {
        return fn (Api $api) => $api->authorize(
            TagType::class,
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

    /**
     * @return array{0: Author, 1: Tag[]}
     */
    protected function authorWithTags(): array
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $tags = [
            Tag::factory()->create(['name' => 'ok tag']),
            Tag::factory()->create(['name' => 'blocked tag'])
        ];
        $author->tags()->attach($tags);
        return [$author, $tags];
    }
}
