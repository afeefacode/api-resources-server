<?php

namespace Afeefa\ApiResources\Test\Eloquent;

use Afeefa\ApiResources\Test\ApiResourcesTest;

class ApiResourcesEloquentTest extends ApiResourcesTest
{
    protected function modelTypeBuilder(): ModelTypeBuilder
    {
        return (new ModelTypeBuilder($this->container));
    }
}
