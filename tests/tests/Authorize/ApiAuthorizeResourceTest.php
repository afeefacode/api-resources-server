<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Api\Authorizator;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Profile;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Tag;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\AuthorResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\ProfileResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Resources\TagResource;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\V2\Operation;
use Closure;

/**
 * Rules registered for a resource instead of for a type.
 *
 * Every case comes in pairs: the direct call of the resource feels the rule,
 * the same type reached through a relation does not.
 */
class ApiAuthorizeResourceTest extends ApiResourcesAuthorizeTest
{
    // narrowing a direct call

    public function test_read_narrows_the_list_of_the_resource()
    {
        $this->threeAuthors();

        ['data' => $data, 'meta' => $meta] = $this->request($this->onlyOkAuthorResource(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertEqualsCanonicalizing(['ok one', 'ok two'], array_column($data, 'name'));
        $this->assertEquals(2, $meta['count_all']);
    }

    public function test_read_narrows_get_of_the_resource()
    {
        [, , $blocked] = $this->threeAuthors();

        $this->expectException(NotFoundException::class);

        $this->request($this->onlyOkAuthorResource(), [
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $blocked->id],
            'fields' => ['name' => true]
        ]);
    }

    public function test_read_leaves_the_same_type_in_a_relation_untouched()
    {
        $blocked = Author::factory()->create(['name' => 'blocked']);
        Article::factory()->create(['title' => 'an article', 'author_id' => $blocked->id]);

        ['data' => $data] = $this->request($this->onlyOkAuthorResource(), [
            'resource' => 'Blog.ArticleResource',
            'action' => 'list',
            'fields' => ['title' => true, 'author' => ['name' => true]]
        ]);

        // the rule of Blog.AuthorResource is not on this path
        $this->assertEquals('blocked', $data[0]['author']['name']);
    }

    public function test_read_leaves_a_relation_count_untouched()
    {
        $author = Author::factory()->create();
        $article = Article::factory()->create(['author_id' => $author->id]);
        $article->tags()->attach([
            Tag::factory()->create(['name' => 'ok tag'])->id,
            Tag::factory()->create(['name' => 'blocked tag'])->id
        ]);

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(
                TagResource::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            ),
            [
                'resource' => 'Blog.ArticleResource',
                'action' => 'list',
                'fields' => ['title' => true, 'count_tags' => true]
            ]
        );

        $this->assertEquals(2, $data[0]['count_tags']);
    }

    // locking a direct write

    public function test_write_false_refuses_a_direct_save_and_writes_nothing()
    {
        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorResource::class)->write(false),
                [
                    'resource' => 'Blog.AuthorResource',
                    'action' => 'save',
                    'params' => ['id' => null],
                    'data' => ['name' => 'ok one', 'email' => 'ok@one'],
                    'fields' => ['name' => true]
                ]
            );
        } finally {
            $this->assertEquals(0, Author::count());
        }
    }

    public function test_write_false_leaves_a_nested_save_of_the_same_type_untouched()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->request(
            fn (Api $api) => $api->authorize(ProfileResource::class)->write(false),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $author->id],
                'data' => ['profile' => ['about_me' => 'about me']],
                'fields' => ['name' => true]
            ]
        );

        // Blog.ProfileResource is locked, the profile under an author is not
        $this->assertEquals('about me', Profile::first()->about_me);
    }

    public function test_delete_false_keeps_the_row()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->expectException(NotFoundException::class);

        try {
            $this->request(
                fn (Api $api) => $api->authorize(AuthorResource::class)->delete(false),
                [
                    'resource' => 'Blog.AuthorResource',
                    'action' => 'save',
                    'params' => ['id' => $author->id],
                    'data' => null,
                    'fields' => ['name' => true]
                ]
            );
        } finally {
            $this->assertEquals(1, Author::count());
        }
    }

    public function test_create_false_leaves_update_open()
    {
        $author = Author::factory()->create(['name' => 'ok one']);

        $this->request(
            fn (Api $api) => $api->authorize(AuthorResource::class)->create(false),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'save',
                'params' => ['id' => $author->id],
                'data' => ['name' => 'ok two'],
                'fields' => ['name' => true]
            ]
        );

        $this->assertEquals('ok two', Author::find($author->id)->name);
    }

    // locking a single action

    public function test_action_false_refuses_an_own_action()
    {
        $api = $this->api(fn (Api $api) => $api->authorize(RoleListResource::class)->action('list_roles', false));
        $api->getResources()->add(RoleListResource::class);

        $this->expectException(NotFoundException::class);

        $api->requestFromInput([
            'resource' => 'Blog.RoleListResource',
            'action' => 'list_roles',
            'fields' => ['name' => true]
        ]);
    }

    public function test_an_own_action_without_a_lock_answers()
    {
        $api = $this->api();
        $api->getResources()->add(RoleListResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.RoleListResource',
            'action' => 'list_roles',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    public function test_action_false_on_list_leaves_get_open()
    {
        [$author] = $this->threeAuthors();

        $lockList = fn (Api $api) => $api->authorize(AuthorResource::class)->action('list', false);

        ['data' => $data] = $this->request($lockList, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'get',
            'params' => ['id' => $author->id],
            'fields' => ['name' => true]
        ]);

        $this->assertEquals('ok one', $data['name']);

        $this->expectException(NotFoundException::class);

        $this->request($lockList, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);
    }

    // schema

    public function test_a_locked_action_is_missing_from_the_schema()
    {
        $api = $this->schemaApi(fn (Api $api) => $api->authorize(AuthorResource::class)->action('list', false));

        $actions = $api->toSchemaJson()['resources']['Blog.AuthorResource'];

        $this->assertArrayNotHasKey('list', $actions);
        $this->assertArrayHasKey('get', $actions);
        $this->assertArrayHasKey('save', $actions);
    }

    public function test_a_resource_whose_only_action_is_locked_is_missing_entirely()
    {
        $api = $this->schemaApi(fn (Api $api) => $api->authorize(RoleListResource::class)->action('list_roles', false));

        $resources = $api->toSchemaJson()['resources'];

        $this->assertArrayNotHasKey('Blog.RoleListResource', $resources);
        $this->assertArrayHasKey('Blog.AuthorResource', $resources);
    }

    public function test_without_a_lock_every_action_is_in_the_schema()
    {
        $actions = $this->schemaApi()->toSchemaJson()['resources']['Blog.AuthorResource'];

        $this->assertEqualsCanonicalizing(['list', 'get', 'save'], array_keys($actions));
    }

    public function test_a_closed_create_leaves_the_create_fields_out_of_the_schema()
    {
        $api = $this->schemaApi(fn (Api $api) => $api->authorize(AuthorType::class)->create(false));

        $type = $api->toSchemaJson()['types']['Blog.Author'];

        $this->assertArrayNotHasKey('create_fields', $type);
        $this->assertArrayHasKey('update_fields', $type);
        $this->assertArrayHasKey('fields', $type);
    }

    public function test_a_closed_update_leaves_the_update_fields_out_of_the_schema()
    {
        $api = $this->schemaApi(fn (Api $api) => $api->authorize(AuthorType::class)->update(false));

        $type = $api->toSchemaJson()['types']['Blog.Author'];

        $this->assertArrayNotHasKey('update_fields', $type);
        $this->assertArrayHasKey('create_fields', $type);
    }

    public function test_a_resource_that_closes_create_keeps_the_create_fields_of_the_type()
    {
        // the bags of a type belong to every resource that exposes it - a rule
        // for one resource cannot speak for the others
        $api = $this->schemaApi(fn (Api $api) => $api->authorize(AuthorResource::class)->create(false));

        $this->assertArrayHasKey('create_fields', $api->toSchemaJson()['types']['Blog.Author']);
    }

    // registration

    public function test_a_closure_for_an_action_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/can only be locked with false/');

        $this->api(fn (Api $api) => $api->authorize(AuthorResource::class)
            ->action('list', fn (EloquentAuthContext $c) => $c->deny()));
    }

    public function test_an_action_the_resource_does_not_have_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/does not have an action list_roles/');

        $this->api(fn (Api $api) => $api->authorize(AuthorResource::class)->action('list_roles', false));
    }

    public function test_action_on_a_type_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/is a type/');

        $this->api(fn (Api $api) => $api->authorize(AuthorType::class)->action('list', false));
    }

    public function test_a_class_that_is_neither_a_type_nor_a_resource_throws()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/is neither/');

        $this->api(fn (Api $api) => $api->authorize(Author::class));
    }

    // identity and composition

    public function test_a_resource_subclass_with_the_same_type_string_shares_the_rule()
    {
        $this->threeAuthors();

        $api = $this->api($this->onlyOkAuthorResource());
        $api->getResources()->add(ProjectAuthorResource::class); // same type string, replaces the entry

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    public function test_the_rule_of_the_type_and_the_rule_of_the_resource_both_apply()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one', 'email' => 'keep@one'],
            ['name' => 'ok two', 'email' => 'drop@two'],
            ['name' => 'blocked', 'email' => 'keep@three']
        )->create();

        ['data' => $data] = $this->request(
            function (Api $api) {
                $api->authorize(AuthorType::class, fn (EloquentAuthContext $c)
                    => $c->query()->where('email', 'like', 'keep%'));
                $api->authorize(AuthorResource::class, fn (EloquentAuthContext $c)
                    => $c->query()->where('name', 'like', 'ok%'));
            },
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertEquals(['ok one'], array_column($data, 'name'));
    }

    public function test_reset_drops_a_locked_action()
    {
        $this->threeAuthors();

        ['data' => $data] = $this->request(
            function (Api $api) {
                $api->authorize(AuthorResource::class)->action('list', false);
                $api->authorize(AuthorResource::class)->reset();
            },
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertCount(3, $data);
    }

    public function test_an_own_action_can_apply_the_rule_of_its_resource_itself()
    {
        $this->threeAuthors();

        $api = $this->api(fn (Api $api) => $api->authorize(
            OwnQueryAuthorResource::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        ));
        $api->getResources()->add(OwnQueryAuthorResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.OwnQueryAuthorResource',
            'action' => 'list_authors',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    /**
     * An api of its own for the schema tests: the blog api registers a
     * resource without a model type, so its schema cannot be built at all.
     */
    protected function schemaApi(?Closure $configureAuth = null): Api
    {
        SchemaAuthorApi::$configureAuthCallback = $configureAuth;
        return (new ApiResources())->getApi(SchemaAuthorApi::class);
    }

    /**
     * @return Author[]
     */
    protected function threeAuthors(): array
    {
        return Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create()->all();
    }

    protected function onlyOkAuthorResource(): Closure
    {
        return fn (Api $api) => $api->authorize(
            AuthorResource::class,
            fn (EloquentAuthContext $c) => $c->query()->where($c->getTablePrefix() . '.name', 'like', 'ok%')
        );
    }
}

class ProjectAuthorResource extends AuthorResource
{
}

class SchemaAuthorApi extends Api
{
    public static ?Closure $configureAuthCallback = null;

    protected static string $type = 'Blog.SchemaAuthorApi';

    protected function resources(ResourceBag $resources): void
    {
        $resources
            ->add(AuthorResource::class)
            ->add(RoleListResource::class);
    }

    protected function configureAuth(): void
    {
        if (static::$configureAuthCallback) {
            (static::$configureAuthCallback)($this);
        }
    }
}

class RoleType extends Type
{
    protected static string $type = 'Blog.Role';

    protected function fields(FieldBag $fields): void
    {
        $fields->string('name');
    }
}

/**
 * An action of a resource's own: it builds its answer itself, the framework
 * does not see into it. Only a lock reaches it.
 */
class RoleListResource extends Resource
{
    protected static string $type = 'Blog.RoleListResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->query('list_roles', Type::list(RoleType::class), function (Action $action) {
            $action->resolve(function (QueryActionResolver $r) {
                $r->get(fn () => Model::fromList(RoleType::type(), [
                    ['id' => '1', 'name' => 'admin'],
                    ['id' => '2', 'name' => 'editor']
                ]));
            });
        });
    }
}

/**
 * An action that builds its own query and applies the rule of its resource.
 */
class OwnQueryAuthorResource extends ModelResource
{
    protected static string $type = 'Blog.OwnQueryAuthorResource';

    public string $ModelTypeClass = AuthorType::class;

    protected function actions(ActionBag $actions): void
    {
        parent::actions($actions);

        $actions->query('list_authors', Type::list(AuthorType::class), function (Action $action) {
            $action->resolve(function (QueryActionResolver $r, Authorizator $authorizator) {
                $r->get(function (ApiRequest $request, Closure $getSelectFields) use ($authorizator) {
                    $query = Author::query();
                    $authorizator->applyAuthorizeResource(
                        OwnQueryAuthorResource::class,
                        Operation::READ,
                        new EloquentAuthContext($query)
                    );
                    return $query->select($getSelectFields())->get()->all();
                });
            });
        });
    }
}
