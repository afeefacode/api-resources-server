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

    public function getTypeClass(): ?string
    {
        return $this->TypeClass ?? null;
    }

    public function typeClasses(array $TypeClasses): ActionResponse
    {
        $this->TypeClasses = $TypeClasses;
        return $this;
    }

    public function getTypeClasses(): array
    {
        return $this->TypeClasses ?? [];
    }

    public function list(): ActionResponse
    {
        $this->list = true;
        return $this;
    }

    public function isList(): bool
    {
        return $this->list;
    }

    public function getSchemaJson(TypeRegistry $typeRegistry): array
    {
        $typeRegistry->registerType($this->TypeClass);

        $json = [
            'type' => $this->TypeClass::type()
        ];

        if ($this->list) {
            $json['list'] = true;
        }

        return $json;
    }
}
