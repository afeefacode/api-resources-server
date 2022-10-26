<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Field\FieldBag;
use Closure;

trait TypeBuilderTrait
{
    public static ?Closure $fieldsCallback;
    public static ?Closure $updateFieldsCallback;
    public static ?Closure $createFieldsCallback;

    protected function fields(FieldBag $fields): void
    {
        if (static::$fieldsCallback) {
            (static::$fieldsCallback)($fields);
        }
    }

    protected function updateFields(FieldBag $updateFields): void
    {
        if (static::$updateFieldsCallback) {
            (static::$updateFieldsCallback)($updateFields);
        }
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
        if (static::$createFieldsCallback) {
            (static::$createFieldsCallback)($createFields, $updateFields);
        }
    }
}
