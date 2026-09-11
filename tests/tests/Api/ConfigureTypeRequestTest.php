<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Resolver\MutationActionResolver;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use Afeefa\ApiResources\Type\Type;

class ConfigureTypeRequestTest extends ApiResourcesTest
{
    public function test_write_false_is_enforced_in_a_save_request()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeRequestApi::class);

        $this->save($api, ['title' => 'title1', 'note' => 'note1']);

        $this->assertEquals(['title' => 'title1'], ConfigureTypeRequestWatcher::$saveFields);
    }

    public function test_only_is_enforced_in_a_save_request()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeOnlyRequestApi::class);

        $this->save($api, ['title' => 'title1', 'note' => 'note1']);

        $this->assertEquals(['title' => 'title1'], ConfigureTypeRequestWatcher::$saveFields);
    }

    public function test_read_only_is_enforced_in_a_save_request()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeReadOnlyRequestApi::class);

        $this->save($api, ['title' => 'title1', 'note' => 'note1']);

        $this->assertEquals([], ConfigureTypeRequestWatcher::$saveFields);
    }

    public function test_only_is_enforced_in_a_read_request()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeOnlyRequestApi::class);

        $this->load($api, ['title' => true, 'note' => true]);

        $this->assertEquals(['id', 'title'], ConfigureTypeRequestWatcher::$selectFields);
    }

    public function test_configuration_is_applied_once_per_type_instance()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeRequestApi::class);

        // The schema request configures the type instances of this container. Applying
        // the same operations a second time would throw, since the field is gone by then.
        $schema = $api->toSchemaJson();
        $this->assertEquals(['title'], array_keys($schema['types']['Test.ConfigureRequest']['update_fields']));

        $this->save($api, ['title' => 'title1', 'note' => 'note1']);

        $this->assertEquals(['title' => 'title1'], ConfigureTypeRequestWatcher::$saveFields);
    }

    protected function setUp(): void
    {
        parent::setUp();

        ConfigureTypeRequestWatcher::$saveFields = null;
        ConfigureTypeRequestWatcher::$selectFields = null;
    }

    private function save(Api $api, array $data): void
    {
        $api->request(function (ApiRequest $request) use ($data) {
            $request
                ->resourceType('Test.ConfigureRequestResource')
                ->actionName('save')
                ->fieldsToSave($data);
        });
    }

    private function load(Api $api, array $fields): void
    {
        $api->request(function (ApiRequest $request) use ($fields) {
            $request
                ->resourceType('Test.ConfigureRequestResource')
                ->actionName('list')
                ->fields($fields);
        });
    }
}

class ConfigureTypeRequestWatcher
{
    public static ?array $saveFields = null;

    public static ?array $selectFields = null;
}

class ConfigureTypeRequestType extends Type
{
    protected static string $type = 'Test.ConfigureRequest';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('title', StringAttribute::class)
            ->attribute('note', StringAttribute::class);
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        $this->fields($updateFields);
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        $this->fields($createFields);
    }
}

class ConfigureTypeRequestResource extends Resource
{
    protected static string $type = 'Test.ConfigureRequestResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->mutation('save', ConfigureTypeRequestType::class, function (Action $action) {
            $action->resolve(function (MutationActionResolver $r) {
                $r->save(function (ApiRequest $request, array $saveFields) {
                    ConfigureTypeRequestWatcher::$saveFields = $saveFields;
                    return Model::fromSingle(ConfigureTypeRequestType::type(), ['id' => '1']);
                });
            });
        });

        $actions->query('list', Type::list(ConfigureTypeRequestType::class), function (Action $action) {
            $action->resolve(function (QueryActionResolver $r) {
                $r->get(function () use ($r) {
                    ConfigureTypeRequestWatcher::$selectFields = $r->getSelectFields();
                    return [];
                });
            });
        });
    }
}

class ConfigureTypeRequestApi extends Api
{
    protected static string $type = 'Test.ConfigureTypeRequestApi';

    protected function resources(ResourceBag $resources): void
    {
        $resources->add(ConfigureTypeRequestResource::class);
    }

    protected function configureTypes(): void
    {
        $this->configureType(ConfigureTypeRequestType::class)->field('note')->write(false);
    }
}

class ConfigureTypeOnlyRequestApi extends ConfigureTypeRequestApi
{
    protected static string $type = 'Test.ConfigureTypeOnlyRequestApi';

    protected function configureTypes(): void
    {
        $this->configureType(ConfigureTypeRequestType::class)->only(['title']);
    }
}

class ConfigureTypeReadOnlyRequestApi extends ConfigureTypeRequestApi
{
    protected static string $type = 'Test.ConfigureTypeReadOnlyRequestApi';

    protected function configureTypes(): void
    {
        $this->configureType(ConfigureTypeRequestType::class)->readOnly();
    }
}
