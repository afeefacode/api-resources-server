<?php

use Afeefa\ApiResources\Test\TestType;

return new class () extends TestType {
    public static string $type = 'Test.Type';

    public static ?Closure $fieldsCallback;
    public static ?Closure $updateFieldsCallback;
    public static ?Closure $createFieldsCallback;
};
