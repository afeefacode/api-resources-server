<?php

use Afeefa\ApiResources\Test\TestApi;

return new class () extends TestApi {
    public static string $type = 'Test.Api';

    public static ?Closure $resourcesCallback;
};
