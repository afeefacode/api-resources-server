<?php

namespace Afeefa\ApiResources\TestV2;

use Afeefa\ApiResources\V2\FieldBag;
use Closure;

trait TypeBuilderTrait
{
    public static ?Closure $fieldsCallback;

    protected function defineFields(FieldBag $fields): void
    {
        if (static::$fieldsCallback) {
            (static::$fieldsCallback)($fields);
        }
    }
}
