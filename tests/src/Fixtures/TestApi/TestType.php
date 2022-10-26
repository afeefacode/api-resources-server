<?php

namespace Afeefa\ApiResources\Test\Fixtures\TestApi;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Type\Type;

class TestType extends Type
{
    protected static string $type = 'TestType';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('attr1', StringAttribute::class)
            ->attribute('attr2', StringAttribute::class)
            ->attribute('attr3', StringAttribute::class);
    }
}
