<?php

use Afeefa\ApiResources\Test\TestValidator;

return new class () extends TestValidator {
    public static string $type = 'Test.Validator';

    public static ?Closure $rulesCallback;
};
