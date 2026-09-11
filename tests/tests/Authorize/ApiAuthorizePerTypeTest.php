<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\AuthorizeBlogApi;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Comment;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\ArticleType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\CommentType;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\V2\Operation;
use Closure;

/**
 * authorizeMorph() on a relation with more than one possible target type.
 *
 * A comment owner is an author or an article, so its rule is not known before
 * the morph column is read. The call asks per declared target type and puts the
 * rule of that very type on the query of its table.
 */
class ApiAuthorizePerTypeTest extends ApiResourcesAuthorizeTest
{
    protected function setUp(): void
    {
        parent::setUp();
        OverridingBlogApi::$overrides = [];
    }

    // the parent row falls away

    public function test_authorize_morph_drops_a_comment_whose_owner_is_out_of_reach()
    {
        $this->fourCommentsOnTwoTypes();

        ['data' => $data] = $this->request(
            function (Api $api) {
                // one rule per type, each on a column only its own table has:
                // were the branches to be mixed up, the database would reject
                // the statement over the missing column
                $api->authorize(
                    AuthorType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%')
                );
                $api->authorize(
                    ArticleType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('title', 'like', 'article-ok%')
                );
                $api->authorize(
                    CommentType::class,
                    fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
                );
            },
            $this->listComments()
        );

        $this->assertEquals(
            ['on article-ok', 'on author-ok'],
            $this->texts($data)
        );
    }

    public function test_a_type_whose_rule_lets_nothing_through_loses_all_its_comments()
    {
        $this->fourCommentsOnTwoTypes();

        ['data' => $data] = $this->request(
            function (Api $api) {
                // the dividing line is the type, not a column value
                $api->authorize(AuthorType::class, fn (EloquentAuthContext $c) => $c->query()->whereRaw('1 = 0'));
                $api->authorize(ArticleType::class, fn (EloquentAuthContext $c) => $c->query()->whereRaw('1 = 1'));
                $api->authorize(
                    CommentType::class,
                    fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
                );
            },
            $this->listComments()
        );

        $this->assertEquals(
            ['on article-no', 'on article-ok'],
            $this->texts($data)
        );
    }

    public function test_a_target_type_without_a_rule_keeps_its_comments()
    {
        $this->fourCommentsOnTwoTypes();

        ['data' => $data] = $this->request(
            function (Api $api) {
                // no rule for Blog.Article, so nothing narrows that branch
                $api->authorize(
                    AuthorType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%')
                );
                $api->authorize(
                    CommentType::class,
                    fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
                );
            },
            $this->listComments()
        );

        $this->assertEquals(
            ['on article-no', 'on article-ok', 'on author-ok'],
            $this->texts($data)
        );
    }

    // the rule of a target type reaches for a rule of its own

    public function test_a_target_types_rule_may_call_authorize_morph_again()
    {
        $ok = Author::factory()->create(['name' => 'author-ok']);
        $no = Author::factory()->create(['name' => 'author-no']);

        // one level: the owner is an author
        $onOk = $this->comment('level 1 ok', $ok);
        $onNo = $this->comment('level 1 no', $no);

        // two levels: the owner is a comment whose own owner is an author
        $this->comment('level 2 ok', $onOk);
        $this->comment('level 2 no', $onNo);

        $api = $this->api(function (Api $api) {
            $api->authorize(
                AuthorType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%')
            );
            // the rule the nested branch reaches, one level down
            $api->authorize(
                CommentType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
            );
            $api->authorize(
                NestedCommentType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
            );
        });
        $api->getResources()->add(NestedCommentResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.NestedCommentResource',
            'action' => 'list',
            'fields' => ['text' => true]
        ]);

        $this->assertEquals(
            ['level 1 ok', 'level 2 ok'],
            $this->texts($data)
        );
    }

    // a relation may point at the type it sits on

    public function test_a_relation_pointing_at_its_own_type_is_narrowed_one_level()
    {
        $ok = Author::factory()->create(['name' => 'author-ok']);
        $no = Author::factory()->create(['name' => 'author-no']);

        $onOk = $this->comment('on ok', $ok);
        $onNo = $this->comment('on no', $no);

        $this->comment('on comment ok', $onOk);
        $this->comment('on comment no', $onNo);

        $api = $this->overridingApi(
            ['Blog.Comment' => SelfOwningCommentType::class],
            function (Api $api) {
                $api->authorize(
                    AuthorType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%')
                );
                // the comment branch reaches this very rule again
                $api->authorize(
                    CommentType::class,
                    fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
                );
            }
        );

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.CommentResource',
            'action' => 'list',
            'fields' => ['text' => true]
        ]);

        // one level deep: the author branch decides, and a comment on a comment
        // is let through instead of asking the same relation again forever
        $this->assertEquals(
            ['on comment no', 'on comment ok', 'on ok'],
            $this->texts($data)
        );
    }

    // the operation argument

    public function test_the_operation_argument_picks_the_rule_of_that_operation()
    {
        $comment = $this->comment('before', Author::factory()->create(['name' => 'author-ok']));

        $this->updateOwnerApi()->requestFromInput($this->saveComment($comment->id));

        $this->assertEquals('changed', Comment::find($comment->id)->text);
    }

    public function test_an_update_whose_owner_is_out_of_reach_is_blocked()
    {
        $comment = $this->comment('before', Author::factory()->create(['name' => 'author-no']));

        $this->expectException(NotFoundException::class);

        try {
            $this->updateOwnerApi()->requestFromInput($this->saveComment($comment->id));
        } finally {
            $this->assertEquals('before', Comment::find($comment->id)->text);
        }
    }

    public function test_the_operation_argument_picks_the_field_bag_of_that_operation()
    {
        $writer = Author::factory()->create(['name' => 'author-ok']);
        $comment = $this->comment('before', Article::factory()->for($writer)->create(['title' => 'an article']));

        // the read bag of the type has Blog.Article as a target of owner, its
        // update bag does not - so on update that branch does not exist
        $this->expectException(NotFoundException::class);

        try {
            $this->updateOwnerApi()->requestFromInput($this->saveComment($comment->id));
        } finally {
            $this->assertEquals('before', Comment::find($comment->id)->text);
        }
    }

    public function test_a_relation_the_bag_of_that_operation_does_not_have_is_reported()
    {
        // Blog.Author has articles on read and in neither write bag
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/relation articles, which Blog.Author does not have for create/');

        $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class)
                ->create(fn (EloquentAuthContext $c) => $c->authorizeMorph('articles', Operation::CREATE)),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => null],
                'data' => ['name' => 'a new author', 'email' => 'new@author'],
                'fields' => ['name' => true]
            ]
        );
    }

    // what the call needs of the schema

    public function test_a_relation_that_is_not_polymorphic_is_reported()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/relation author of Blog.Article, which is not polymorphic/');

        $this->request(
            fn (Api $api) => $api->authorize(
                ArticleType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('author')
            ),
            [
                'resource' => 'Blog.ArticleResource',
                'action' => 'list',
                'fields' => ['title' => true]
            ]
        );
    }

    public function test_a_target_type_without_an_eloquent_model_is_reported()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/every target of owner, but Blog.PlainOwner does not have one/');

        $api = $this->overridingApi(
            ['Blog.Comment' => PlainTargetCommentType::class],
            fn (Api $api) => $api->authorize(
                CommentType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
            )
        );

        $api->requestFromInput([
            'resource' => 'Blog.CommentResource',
            'action' => 'list',
            'fields' => ['text' => true]
        ]);
    }

    public function test_the_declared_targets_are_those_of_an_overridden_type()
    {
        $this->fourCommentsOnTwoTypes();

        // Blog.Comment is swapped for a type that declares one target only, so
        // the article branch is not part of the statement at all
        $api = $this->overridingApi(
            ['Blog.Comment' => AuthorOnlyCommentType::class],
            fn (Api $api) => $api->authorize(
                CommentType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('owner')
            )
        );

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.CommentResource',
            'action' => 'list',
            'fields' => ['text' => true]
        ]);

        $this->assertEquals(['on author-no', 'on author-ok'], $this->texts($data));
    }

    // the contrast: without authorizeMorph the row stays and the relation is empty

    public function test_without_authorize_morph_the_comment_stays_and_its_owner_is_empty()
    {
        $this->fourCommentsOnTwoTypes();

        ['data' => $data] = $this->request(
            function (Api $api) {
                $api->authorize(
                    AuthorType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%')
                );
                $api->authorize(
                    ArticleType::class,
                    fn (EloquentAuthContext $c) => $c->query()->where('title', 'like', 'article-ok%')
                );
            },
            $this->listComments()
        );

        $owners = [];
        foreach ($data as $comment) {
            $owners[$comment['text']] = $comment['owner']['name'] ?? $comment['owner']['title'] ?? null;
        }

        $this->assertCount(4, $data); // no rule on the comment itself, so no comment is dropped
        $this->assertEquals('author-ok', $owners['on author-ok']);
        $this->assertNull($owners['on author-no']);
        $this->assertEquals('article-ok', $owners['on article-ok']);
        $this->assertNull($owners['on article-no']);
    }

    // outside a registered rule there is no rule to look up

    public function test_authorize_morph_on_a_hand_built_context_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/only works in a rule registered with Api::authorize/');

        (new EloquentAuthContext(Comment::query()))->authorizeMorph('owner');
    }

    public function test_a_context_carries_no_rule_once_that_rule_has_run()
    {
        $kept = null;

        $this->request(
            function (Api $api) use (&$kept) {
                // an arrow function would capture $kept by value
                $api->authorize(CommentType::class, function (EloquentAuthContext $c) use (&$kept) {
                    $kept = $c;
                });
            },
            $this->listComments()
        );

        // whoever holds on to a context afterwards holds nothing
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/only works in a rule registered with Api::authorize/');

        $kept->authorizeMorph('owner');
    }

    public function test_authorize_morph_names_a_relation_the_type_does_not_have()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/relation ownre, which Blog.Comment does not have/');

        $this->request(
            fn (Api $api) => $api->authorize(
                CommentType::class,
                fn (EloquentAuthContext $c) => $c->authorizeMorph('ownre')
            ),
            $this->listComments()
        );
    }

    protected function overridingApi(array $overrides, Closure $configureAuth): Api
    {
        OverridingBlogApi::$overrides = $overrides;
        AuthorizeBlogApi::$configureAuthCallback = $configureAuth;
        return (new ApiResources())->getApi(OverridingBlogApi::class);
    }

    /**
     * Narrows an update of a comment by the update rule of its owner.
     */
    protected function updateOwnerApi(): Api
    {
        return $this->overridingApi(
            ['Blog.Comment' => UpdateOwnerCommentType::class],
            function (Api $api) {
                $api->authorize(AuthorType::class)
                    ->update(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'author-ok%'));
                $api->authorize(CommentType::class)
                    ->update(fn (EloquentAuthContext $c) => $c->authorizeMorph('owner', Operation::UPDATE));
            }
        );
    }

    protected function saveComment(?string $id, array $data = ['text' => 'changed']): array
    {
        return [
            'resource' => 'Blog.CommentResource',
            'action' => 'save',
            'params' => ['id' => $id],
            'data' => $data,
            'fields' => ['text' => true]
        ];
    }

    protected function fourCommentsOnTwoTypes(): void
    {
        $this->comment('on author-ok', Author::factory()->create(['name' => 'author-ok']));
        $this->comment('on author-no', Author::factory()->create(['name' => 'author-no']));

        // an article needs an author; that one owns no comment and stays out of it
        $writer = Author::factory()->create(['name' => 'writer']);
        $this->comment('on article-ok', Article::factory()->for($writer)->create(['title' => 'article-ok']));
        $this->comment('on article-no', Article::factory()->for($writer)->create(['title' => 'article-no']));
    }

    protected function listComments(): array
    {
        return [
            'resource' => 'Blog.CommentResource',
            'action' => 'list',
            'fields' => [
                'text' => true,
                'owner' => [
                    '@Blog.Author' => ['name' => true],
                    '@Blog.Article' => ['title' => true]
                ]
            ]
        ];
    }

    /**
     * The texts of the returned comments, sorted - a list is not ordered here.
     */
    protected function texts(array $data): array
    {
        $texts = array_map(fn ($comment) => $comment['text'], $data);
        sort($texts);
        return $texts;
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

/**
 * A second type on the comments table, so that Blog.Comment can be a target
 * type without being the type that is listed.
 */
class NestedComment extends Comment
{
    public static $type = 'Blog.NestedComment';
}

/**
 * A comment whose owner may be a comment as well, so the rule of a target type
 * is one that calls authorizeMorph() itself.
 */
class NestedCommentType extends CommentType
{
    protected static string $type = 'Blog.NestedComment';

    public static string $ModelClass = NestedComment::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('text')

            ->hasOne('owner', [AuthorType::class, CommentType::class]);
    }
}

class NestedCommentResource extends ModelResource
{
    protected static string $type = 'Blog.NestedCommentResource';

    public string $ModelTypeClass = NestedCommentType::class;
}

/**
 * Blog api whose type overrides are handed in per test case.
 */
class OverridingBlogApi extends AuthorizeBlogApi
{
    protected static string $type = 'Blog.OverridingBlogApi';

    public static array $overrides = [];

    protected function overrideTypes(): array
    {
        return static::$overrides;
    }
}

/**
 * A comment whose owner may be a comment, which is the type this is registered
 * as - so the rule of the target is the rule that is running.
 */
class SelfOwningCommentType extends CommentType
{
    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('text')

            ->hasOne('owner', [AuthorType::class, CommentType::class]);
    }
}

/**
 * A comment whose owner is declared differently per bag: both types on read,
 * an author only on update.
 */
class UpdateOwnerCommentType extends CommentType
{
    protected function updateFields(FieldBag $updateFields): void
    {
        $updateFields
            ->string('text')

            ->linkOne('owner', [AuthorType::class]);
    }
}

/**
 * A comment with a single declared owner type, which is polymorphic all the
 * same.
 */
class AuthorOnlyCommentType extends CommentType
{
    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('text')

            ->hasOne('owner', [AuthorType::class]);
    }
}

class PlainOwnerType extends Type
{
    protected static string $type = 'Blog.PlainOwner';

    protected function fields(FieldBag $fields): void
    {
        $fields->string('label');
    }
}

/**
 * A comment whose owner may be a type without a table behind it.
 */
class PlainTargetCommentType extends CommentType
{
    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('text')

            ->hasOne('owner', [AuthorType::class, PlainOwnerType::class]);
    }
}
