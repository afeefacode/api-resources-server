<?php

use Afeefa\ApiResources\Test\TestResource;

return new class () extends TestResource {
    public static string $type = 'Test.Resource';

    public static ?Closure $actionsCallback;
};
