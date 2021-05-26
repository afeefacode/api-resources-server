<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;

class DataResolver implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected function resolveContext(): ResolveContext
    {
        return $this->container->create(ResolveContext::class);
    }
}
