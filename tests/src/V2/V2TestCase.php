<?php

namespace Afeefa\ApiResources\TestV2;

use Afeefa\ApiResources\Test\ApiResourcesTest;

class V2TestCase extends ApiResourcesTest
{
    protected function v2TypeBuilder(): TypeBuilder
    {
        return new TypeBuilder($this->container);
    }
}
