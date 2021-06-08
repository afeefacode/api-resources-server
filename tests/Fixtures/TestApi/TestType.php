<?php

namespace Afeefa\ApiResources\Tests\Fixtures\TestApi;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Fields\VarcharAttribute;
use Afeefa\ApiResources\Type\Type;

class TestType extends Type
{
    public static string $type = 'TestType';

    protected function fields(FieldBag $fields): void
    {
        $fields
            ->attribute('attr1', VarcharAttribute::class)
            ->attribute('attr2', VarcharAttribute::class)
            ->attribute('attr3', VarcharAttribute::class);
    }
}
