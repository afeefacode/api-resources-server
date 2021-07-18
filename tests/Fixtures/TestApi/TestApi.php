<?php

namespace Afeefa\ApiResources\Tests\Fixtures\TestApi;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Resource\ResourceBag;

class TestApi extends Api
{
    public static string $type = 'TestApi';

    protected function resources(ResourceBag $resources): void
    {
        $resources
            ->add(TestResource::class);
    }
}
