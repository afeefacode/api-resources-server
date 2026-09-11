<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\AuthContext;
use Afeefa\ApiResources\Api\Authorizator;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\V2\Operation;

class ApiAuthorizeSurfaceTest extends ApiResourcesAuthorizeTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AuthorizeTestService::$allowedName = null;
        FileListResource::$files = [];
        FileListResource::$applyAuthorize = true;
        FileListResource::$context = null;
    }

    // repeated registration

    public function test_a_second_registration_adds_to_the_first()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();

        $author = $this->api(function (Api $api) {
            // a library registers the row rule ...
            $api->authorize(
                AuthorType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            );
            // ... a project refines a single op, without dropping the rest
            $api->authorize(AuthorType::class)
                ->delete(fn (EloquentAuthContext $c) => $c->deny());
        });

        ['data' => $data] = $author->requestFromInput([
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    public function test_reset_drops_what_was_registered_before()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();

        ['data' => $data] = $this->request(function (Api $api) {
            $api->authorize(
                AuthorType::class,
                fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
            );
            $api->authorize(AuthorType::class)->reset();
        }, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(3, $data);
    }

    // type identity

    public function test_a_subclass_with_the_same_type_string_shares_the_rule()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();

        $api = $this->api(fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        ));
        $api->getResources()->add(SameTypeStringAuthorResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.SameTypeStringAuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    public function test_a_subclass_with_an_own_type_string_does_not_inherit_the_rule()
    {
        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();

        $api = $this->api(fn (Api $api) => $api->authorize(
            AuthorType::class,
            fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%')
        ));
        $api->getResources()->add(OwnTypeStringAuthorResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.OwnTypeStringAuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(3, $data);
    }

    // closure invocation

    public function test_the_closure_gets_its_dependencies_from_the_container()
    {
        Author::factory()->count(2)->sequence(['name' => 'ok one'], ['name' => 'blocked'])->create();

        AuthorizeTestService::$allowedName = 'ok one';

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(
                AuthorType::class,
                fn (EloquentAuthContext $c, AuthorizeTestService $service) =>
                    $c->query()->where('name', $service->getAllowedName())
            ),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertEquals('ok one', $data[0]['name']);
        $this->assertCount(1, $data);
    }

    public function test_a_closure_without_parameters_is_called_without_arguments()
    {
        Author::factory()->create();

        AuthorizeTestService::$calls = 0;

        ['data' => $data] = $this->request(
            fn (Api $api) => $api->authorize(AuthorType::class, function () {
                AuthorizeTestService::$calls++;
            }),
            [
                'resource' => 'Blog.AuthorResource',
                'action' => 'list',
                'fields' => ['name' => true]
            ]
        );

        $this->assertEquals(1, AuthorizeTestService::$calls);
        $this->assertCount(1, $data);
    }

    public function test_a_context_the_path_cannot_provide_throws()
    {
        FileListResource::$files = [['id' => '1', 'name' => 'one']];

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/expects a context of type/');

        $api = $this->api(fn (Api $api) => $api->authorize(
            FileType::class,
            // the file path hands in a FileAuthContext, never an Eloquent one
            fn (EloquentAuthContext $c) => $c->query()
        ));
        $api->getResources()->add(FileListResource::class);

        $api->requestFromInput([
            'resource' => 'Blog.FileListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);
    }

    // data source without a query

    public function test_a_own_context_filters_the_result_of_a_own_resolver()
    {
        FileListResource::$files = [
            ['id' => '1', 'name' => 'public one'],
            ['id' => '2', 'name' => 'intern_secret'],
            ['id' => '3', 'name' => 'public two']
        ];

        $api = $this->api(fn (Api $api) => $api->authorize(
            FileType::class,
            fn (FileAuthContext $c) => $c->keep(fn (array $f) => !str_starts_with($f['name'], 'intern_'))
        ));
        $api->getResources()->add(FileListResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.FileListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertEquals(['public one', 'public two'], array_column($data, 'name'));
    }

    public function test_deny_on_a_own_context_aborts_the_request()
    {
        FileListResource::$files = [['id' => '1', 'name' => 'one']];

        $api = $this->api(fn (Api $api) => $api->authorize(
            FileType::class,
            fn (FileAuthContext $c) => $c->deny()
        ));
        $api->getResources()->add(FileListResource::class);

        $this->expectException(NotFoundException::class);

        $api->requestFromInput([
            'resource' => 'Blog.FileListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);
    }

    public function test_a_own_resolver_that_does_not_ask_delivers_unfiltered()
    {
        // documented behaviour, not a framework bug: whoever builds their own
        // query has to hook the rule in themselves
        FileListResource::$files = [
            ['id' => '1', 'name' => 'public one'],
            ['id' => '2', 'name' => 'intern_secret']
        ];
        FileListResource::$applyAuthorize = false;

        $api = $this->api(fn (Api $api) => $api->authorize(
            FileType::class,
            fn (FileAuthContext $c) => $c->keep(fn (array $f) => !str_starts_with($f['name'], 'intern_'))
        ));
        $api->getResources()->add(FileListResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.FileListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }

    public function test_a_own_resolver_without_a_registered_rule_delivers_unfiltered()
    {
        FileListResource::$files = [
            ['id' => '1', 'name' => 'public one'],
            ['id' => '2', 'name' => 'intern_secret']
        ];

        $api = $this->api();
        $api->getResources()->add(FileListResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.FileListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
    }
}

class AuthorizeTestService
{
    public static ?string $allowedName = null;

    public static int $calls = 0;

    public function getAllowedName(): ?string
    {
        return static::$allowedName;
    }
}

class SameTypeStringAuthorType extends AuthorType
{
}

class SameTypeStringAuthorResource extends ModelResource
{
    protected static string $type = 'Blog.SameTypeStringAuthorResource';

    public string $ModelTypeClass = SameTypeStringAuthorType::class;
}

class OwnTypeStringAuthor extends Author
{
    public static $type = 'Blog.OwnTypeStringAuthor';
}

class OwnTypeStringAuthorType extends AuthorType
{
    protected static string $type = 'Blog.OwnTypeStringAuthor';

    public static string $ModelClass = OwnTypeStringAuthor::class;
}

class OwnTypeStringAuthorResource extends ModelResource
{
    protected static string $type = 'Blog.OwnTypeStringAuthorResource';

    public string $ModelTypeClass = OwnTypeStringAuthorType::class;
}

class FileType extends Type
{
    protected static string $type = 'Blog.File';

    protected function fields(FieldBag $fields): void
    {
        $fields->string('name');
    }
}

/**
 * A resource whose rows do not come from a database.
 *
 * It brings its own context and hooks the rule in itself - the framework does
 * not see into it.
 */
class FileAuthContext extends AuthContext
{
    private array $predicates = [];

    public function keep(callable $predicate): void
    {
        $this->predicates[] = $predicate;
    }

    public function apply(array $items): array
    {
        foreach ($this->predicates as $predicate) {
            $items = array_values(array_filter($items, $predicate));
        }
        return $items;
    }
}

class FileListResource extends Resource
{
    public static array $files = [];

    public static bool $applyAuthorize = true;

    public static ?FileAuthContext $context = null;

    protected static string $type = 'Blog.FileListResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->query('list', Type::list(FileType::class), function (Action $action) {
            $action->resolve(function (QueryActionResolver $r, Authorizator $authorizator) {
                $r->get(function () use ($authorizator) {
                    $files = static::$files;

                    if (static::$applyAuthorize) {
                        $context = new FileAuthContext();
                        $authorizator->applyAuthorize(FileType::class, Operation::READ, $context);
                        $files = $context->apply($files);
                    }

                    return Model::fromList(FileType::type(), $files);
                });
            });
        });
    }
}
