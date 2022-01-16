<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;

class TypeClassMap implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected array $map = [];

    public function add(string $TypeClass): void
    {
        $this->map[$TypeClass::type()] = $TypeClass;
    }

    public function get(string $type): ?string
    {
        return $this->map[$type] ?? null;
    }

    public function createUsedTypesForApi(Api $api): array
    {
        $types = [];
        foreach ($api->getResources()->getEntries() as $resource) {
            foreach ($resource->getActions()->getEntries() as $action) {
                $types = $this->createUsedTypesForAction($action, $types);
            }
        };
        return $types;
    }

    public function createUsedTypesForAction(Action $action, array $types = []): array
    {
        $TypeClasses = $action->getResponse()->getAllTypeClasses();
        return $this->createUsedTypes($types, $TypeClasses);
    }

    protected function createUsedTypes(array $types, array $TypeClasses): array
    {
        foreach ($TypeClasses as $TypeClass) {
            if (!isset($types[$TypeClass])) {
                $type = $this->container->get($TypeClass);
                $types[$TypeClass] = $type;
                $this->add(get_class($type));

                $TypeClasses = $type->getAllRelatedTypeClasses();
                $types = $this->createUsedTypes($types, $TypeClasses);
            }
        }
        return $types;
    }
}
