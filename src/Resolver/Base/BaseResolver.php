<?php

namespace Afeefa\ApiResources\Resolver\Base;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Type\TypeClassMap;

class BaseResolver implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected array $resolveContexts = [];

    protected function getTypeByName(string $typeName): Type
    {
        $TypeClass = $this->container->get(TypeClassMap::class)->get($typeName) ?? Type::class;
        return $this->container->get($TypeClass);
    }

    /**
     * @param ModelInterface[] $models
     */
    protected function sortModelsByType(array $models): array
    {
        $modelsByType = [];
        foreach ($models as $model) {
            $type = $model->apiResourcesGetType();
            $modelsByType[$type][] = $model;
        }
        return $modelsByType;
    }
}
