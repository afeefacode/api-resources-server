<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Eloquent\Model as EloquentModel;
use Afeefa\ApiResources\Eloquent\ModelResource;
use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Eloquent\SimpleListAction;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;
use Afeefa\ApiResources\Type\Type;

class ApiAuthorizeQueryShapeTest extends ApiResourcesAuthorizeTest
{
    // getTablePrefix()

    public function test_table_prefix_is_the_plain_table_name_of_a_top_level_query()
    {
        $context = new EloquentAuthContext(Author::query());

        $this->assertEquals('authors', $context->getTablePrefix());
    }

    public function test_table_prefix_is_the_alias_eloquent_assigned()
    {
        $context = new EloquentAuthContext(Author::query()->from('authors as some_alias'));

        $this->assertEquals('some_alias', $context->getTablePrefix());
    }

    public function test_table_prefix_hits_the_aliased_table_of_a_self_relation()
    {
        // Eloquent aliases the target table as soon as it equals the table of
        // the outer query - a hand written name would land on the outer row
        // here and count the wrong rows.
        $root = $this->thread('ok root');
        $this->thread('ok child one', $root);
        $this->thread('ok child two', $root);
        $this->thread('blocked child', $root);

        $api = $this->api(fn (Api $api) => $api->authorize(
            ThreadType::class,
            fn (EloquentAuthContext $c) => $c->query()->where($c->getTablePrefix() . '.text', 'like', 'ok%')
        ));
        $api->getResources()->add(ThreadResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.ThreadResource',
            'action' => 'list',
            'fields' => ['text' => true, 'count_children' => true]
        ]);

        $rows = [];
        foreach ($data as $row) {
            $rows[$row['text']] = $row['count_children'];
        }

        $this->assertEquals(['ok root' => 2, 'ok child one' => 0, 'ok child two' => 0], $rows);
    }

    // SimpleListAction

    public function test_simple_list_action_applies_the_rule_of_its_response_type()
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
        $api->getResources()->add(SimpleAuthorListResource::class);

        ['data' => $data, 'meta' => $meta] = $api->requestFromInput([
            'resource' => 'Blog.SimpleAuthorListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(2, $data);
        $this->assertEquals(2, $meta['count_all']);
    }

    public function test_simple_list_action_without_a_rule_returns_everything()
    {
        Author::factory()->count(3)->create();

        $api = $this->api();
        $api->getResources()->add(SimpleAuthorListResource::class);

        ['data' => $data] = $api->requestFromInput([
            'resource' => 'Blog.SimpleAuthorListResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $this->assertCount(3, $data);
    }

    protected function thread(string $text, ?Thread $owner = null): Thread
    {
        $thread = new Thread();
        $thread->text = $text;
        $thread->owner_id = $owner?->id ?? 0;
        $thread->owner_type = Thread::$type;
        $thread->save();
        return $thread->fresh();
    }
}

/**
 * Self relation on the comments table: a thread owns further threads.
 */
class Thread extends EloquentModel
{
    public static $type = 'Blog.Thread';

    protected $table = 'comments';

    public $timestamps = false;

    public function children()
    {
        return $this->morphMany(Thread::class, 'owner');
    }
}

class ThreadType extends ModelType
{
    protected static string $type = 'Blog.Thread';

    public static string $ModelClass = Thread::class;

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->string('text')
            ->hasMany('children', ThreadType::class);
    }
}

class ThreadResource extends ModelResource
{
    protected static string $type = 'Blog.ThreadResource';

    public string $ModelTypeClass = ThreadType::class;
}

class SimpleAuthorListResource extends \Afeefa\ApiResources\Resource\Resource
{
    protected static string $type = 'Blog.SimpleAuthorListResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->query('list', Type::list(AuthorType::class), function (SimpleListAction $action) {
        });
    }
}
