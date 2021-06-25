<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;

class ActionInput implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;

    protected bool $list = false;
    protected bool $create = false;
    protected bool $update = false;

    protected string $TypeClass;

    public function typeClass(string $TypeClass): ActionInput
    {
        $this->TypeClass = $TypeClass;
        return $this;
    }

    public function getType(): Type
    {
        return $this->container->get($this->TypeClass);
    }

    public function list($list = true): ActionInput
    {
        $this->list = $list;
        return $this;
    }

    public function create($create = true): ActionInput
    {
        $this->create = $create;
        return $this;
    }

    public function update($update = true): ActionInput
    {
        $this->update = $update;
        return $this;
    }

    public function getSchemaJson(TypeRegistry $typeRegistry): array
    {
        $typeRegistry->registerType($this->TypeClass);

        $json = [
            'type' => $this->TypeClass::$type
        ];

        if ($this->list) {
            $json['list'] = true;
        }

        if ($this->create) {
            $json['create'] = true;
        }

        if ($this->update) {
            $json['update'] = true;
        }

        return $json;
    }
}
