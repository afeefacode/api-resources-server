<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Profile;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ArticleType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ProfileType;

class ApiAuthorizePolymorphicTest extends ApiResourcesAuthorizeTest
{
    protected function setUp(): void
    {
        parent::setUp();
        PrefixWatcher::$prefixes = [];
    }

    // getTablePrefix() behind a MorphTo

    public function test_table_prefix_in_a_morph_to_eager_load_names_the_target_table()
    {
        $author = Author::factory()->create(['name' => 'ok one']);
        $this->comment('a comment', $author);

        $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class, function (EloquentAuthContext $c) {
                PrefixWatcher::$prefixes[] = $c->getTablePrefix();
                $c->query()->where($c->getTablePrefix() . '.name', 'like', 'ok%');
            }),
            [
                'resource' => 'Blog.CommentResource',
                'action' => 'list',
                'fields' => ['text' => true, 'owner' => ['name' => true]]
            ]
        );

        // not "comments": until it is loaded a MorphTo sits on the query of its
        // parent, and the rule has to name the table it will actually run on
        $this->assertEquals(['authors'], PrefixWatcher::$prefixes);
    }

    public function test_a_prefixed_column_in_a_nested_condition_reaches_the_target_table()
    {
        $okAuthor = Author::factory()->create(['name' => 'ok one']);
        $alsoAuthor = Author::factory()->create(['name' => 'also fine']);
        $blockedAuthor = Author::factory()->create(['name' => 'blocked']);

        $this->comment('on ok', $okAuthor);
        $this->comment('on also', $alsoAuthor);
        $this->comment('on blocked', $blockedAuthor);

        // Eloquent rewrites the table of a plain where when it replays the
        // constraints per type, but not the one inside a nested condition -
        // a rule that says "a or b" only works with the right prefix.
        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class, fn (EloquentAuthContext $c) => $c->query()
                ->where(fn ($q) => $q
                    ->where($c->getTablePrefix() . '.name', 'like', 'ok%')
                    ->orWhere($c->getTablePrefix() . '.name', 'like', 'also%'))),
            [
                'resource' => 'Blog.CommentResource',
                'action' => 'list',
                'fields' => ['text' => true, 'owner' => ['name' => true]]
            ]
        );

        $owners = [];
        foreach ($data as $comment) {
            $owners[$comment['text']] = $comment['owner']['name'] ?? null;
        }

        $this->assertEquals('ok one', $owners['on ok']);
        $this->assertEquals('also fine', $owners['on also']);
        $this->assertNull($owners['on blocked']);
    }

    // save path: relation with more than one possible target type

    public function test_an_unreadable_existing_link_of_a_union_relation_is_not_unlinked()
    {
        $profile = Profile::factory()->create();
        $blocked = Author::factory()->create(['name' => 'blocked one']);
        $other = Author::factory()->create(['name' => 'ok two']);
        $blocked->profile_id = $profile->id;
        $blocked->save();

        $api = $this->api(fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        ));
        $api->getResources()->add(UnionAuthorProfileResource::class);

        $api->requestFromInput([
            'resource' => 'Blog.UnionAuthorProfileResource',
            'action' => 'save',
            'params' => ['id' => $profile->id],
            'data' => ['author' => ['id' => $other->id, 'type' => Author::$type]],
            'fields' => ['about_me' => true]
        ]);

        // The existing link is out of reach, so it does not show up and is not
        // dropped - even though the relation may point at more than one type
        // and the rule cannot go into the relation query.
        $this->assertEquals($profile->id, Author::find($blocked->id)->profile_id);
        $this->assertEquals($profile->id, Author::find($other->id)->profile_id);
    }

    public function test_a_readable_existing_link_of_a_union_relation_is_unlinked()
    {
        $profile = Profile::factory()->create();
        $existing = Author::factory()->create(['name' => 'ok one']);
        $other = Author::factory()->create(['name' => 'ok two']);
        $existing->profile_id = $profile->id;
        $existing->save();

        $api = $this->api(fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        ));
        $api->getResources()->add(UnionAuthorProfileResource::class);

        $api->requestFromInput([
            'resource' => 'Blog.UnionAuthorProfileResource',
            'action' => 'save',
            'params' => ['id' => $profile->id],
            'data' => ['author' => ['id' => $other->id, 'type' => Author::$type]],
            'fields' => ['about_me' => true]
        ]);

        $this->assertNull(Author::find($existing->id)->profile_id);
        $this->assertEquals($profile->id, Author::find($other->id)->profile_id);
    }

    public function test_a_union_relation_without_a_rule_keeps_every_existing_link_visible()
    {
        $profile = Profile::factory()->create();
        $existing = Author::factory()->create(['name' => 'blocked one']);
        $other = Author::factory()->create(['name' => 'ok two']);
        $existing->profile_id = $profile->id;
        $existing->save();

        $api = $this->api();
        $api->getResources()->add(UnionAuthorProfileResource::class);

        $api->requestFromInput([
            'resource' => 'Blog.UnionAuthorProfileResource',
            'action' => 'save',
            'params' => ['id' => $profile->id],
            'data' => ['author' => ['id' => $other->id, 'type' => Author::$type]],
            'fields' => ['about_me' => true]
        ]);

        $this->assertNull(Author::find($existing->id)->profile_id);
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

class PrefixWatcher
{
    public static array $prefixes = [];
}

/**
 * A profile whose author link is declared for more than one type, so the rule
 * cannot be put into the relation query.
 */
class UnionAuthorProfileType extends ProfileType
{
    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->string('about_me')

            ->linkOne('author', [AuthorType::class, ArticleType::class]);
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $createFields
            ->from($updateFields, 'about_me')

            ->from($updateFields, 'author');
    }
}

class UnionAuthorProfileResource extends ModelResource
{
    protected static string $type = 'Blog.UnionAuthorProfileResource';

    public string $ModelTypeClass = UnionAuthorProfileType::class;
}
