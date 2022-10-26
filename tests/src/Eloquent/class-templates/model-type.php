<?php

use Afeefa\ApiResources\Test\Eloquent\TestModelType;

return new class () extends TestModelType {
    protected static string $type = 'Test.Type';

    public static string $ModelClass;

    public static ?Closure $fieldsCallback;
    public static ?Closure $updateFieldsCallback;
    public static ?Closure $createFieldsCallback;
};
