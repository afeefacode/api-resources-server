<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;

class ActionResponse implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;

    protected bool $list = false;

    protected string $TypeClass;

    protected array $TypeClasses;

    public function typeClass(string $TypeClass): ActionResponse
    {
        $this->TypeClass = $TypeClass;

        return $this;
    }

    public function getTypeClass(): string
    {
        return $this->TypeClass;
    }

    public function types(array $TypeClasses)
    {
        $this->TypeClasses = $TypeClasses;
        return $this;
    }

    public function list()
    {
        $this->list = true;
        return $this;
    }

    public function getSchemaJson(TypeRegistry $typeRegistry): array
    {
        $typeRegistry->registerType($this->TypeClass);

        $json = [
            'type' => $this->TypeClass::$type
        ];

        return $json;
    }
}
