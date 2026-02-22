<?php

use Afeefa\ApiResources\TestV2\TestType;

return new class () extends TestType {
    protected static string $type = 'Test.Type';

    public static ?Closure $fieldsCallback;
};
