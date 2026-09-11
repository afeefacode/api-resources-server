<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Test\ApiResourcesTest;
use Afeefa\ApiResources\Type\Type;

class ConfigureTypeOverrideTest extends ApiResourcesTest
{
    public function test_configure_type_applies_to_the_overridden_type()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeOverrideApi::class);

        $schema = $api->toSchemaJson();
        $fields = $schema['types']['Test.Override']['fields'];

        $this->assertEquals(['attr1'], array_keys($fields));
    }

    public function test_configure_type_applies_without_an_override()
    {
        /** @var Api */
        $api = $this->container->get(ConfigureTypeApi::class);

        $schema = $api->toSchemaJson();
        $fields = $schema['types']['Test.Override']['fields'];

        $this->assertEquals(['attr1'], array_keys($fields));
    }
}

class ConfigureTypeBaseType extends Type
{
    protected static string $type = 'Test.Override';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('attr1', StringAttribute::class)
            ->attribute('attr2', StringAttribute::class)
            ->attribute('attr3', StringAttribute::class);
    }
}

class ConfigureTypeSubType extends ConfigureTypeBaseType
{
}

class ConfigureTypeResource extends Resource
{
    protected static string $type = 'Test.OverrideResource';

    protected function actions(ActionBag $actions): void
    {
        $actions->query('get_type', Type::list(ConfigureTypeBaseType::class), function (Action $action) {
            $action->resolve(function () {
            });
        });
    }
}

class ConfigureTypeApi extends Api
{
    protected static string $type = 'Test.ConfigureTypeApi';

    protected function resources(ResourceBag $resources): void
    {
        $resources->add(ConfigureTypeResource::class);
    }

    protected function configureTypes(): void
    {
        $this->configureType(ConfigureTypeBaseType::class)->only(['attr1']);
    }
}

class ConfigureTypeOverrideApi extends ConfigureTypeApi
{
    protected static string $type = 'Test.ConfigureTypeOverrideApi';

    protected function overrideTypes(): array
    {
        return [ConfigureTypeBaseType::type() => ConfigureTypeSubType::class];
    }
}
