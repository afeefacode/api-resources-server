<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\ContainerAwareInterface;

trait ToSchemaJsonTrait
{
    public function toSchemaJson(): array
    {
        if (method_exists($this, 'getSchemaJson')) {
            if ($this instanceof ContainerAwareInterface && isset($this->container)) {
                return $this->container->call([$this, 'getSchemaJson']);
            }
            return $this->getSchemaJson();
        }
        return [];
    }
}
