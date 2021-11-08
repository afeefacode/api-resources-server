<?php

use Afeefa\ApiResources\Test\TestValidator;

return new class () extends TestValidator {
    protected static string $type = 'Test.Validator';

    public static ?Closure $rulesCallback;
};
