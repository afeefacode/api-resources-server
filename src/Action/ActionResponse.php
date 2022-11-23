<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Type\TypeMeta;
use Closure;

class ActionResponse implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;

    protected bool $list = false;

    protected bool $link = false;

    protected string $TypeClass;

    protected array $TypeClasses;

    public function initFromArgument($TypeClassOrClassesOrMeta, Closure $callback = null): ActionResponse
    {
        $valueFor = $this->getNameForException();
        $argumentName = $this->getArgumentNameForException();

        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $typeMeta = $TypeClassOrClassesOrMeta;
            $TypeClassOrClasses = $typeMeta->TypeClassOrClasses;

            $this->list = $typeMeta->list;
            $this->link = $typeMeta->link;
        } else {
            $TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }

        // make array [Type] to string Type
        if (is_array($TypeClassOrClasses)) {
            $TypeClassOrClasses = array_values(array_unique($TypeClassOrClasses)); // array_values: remove empty keys
        }

        // make array [Type] to string Type
        if (is_array($TypeClassOrClasses) && count($TypeClassOrClasses) === 1) {
            $TypeClassOrClasses = $TypeClassOrClasses[0];
        }

        if (is_array($TypeClassOrClasses)) {
            foreach ($TypeClassOrClasses as $TypeClass) {
                if (!class_exists($TypeClass)) {
                    throw new NotATypeException("Value for {$valueFor} {$argumentName} is not a list of types.");
                }
            }
            $this->typeClasses($TypeClassOrClasses);
        } elseif (is_string($TypeClassOrClasses)) {
            if (!class_exists($TypeClassOrClasses)) {
                throw new NotATypeException("Value for {$valueFor} {$argumentName} is not a type.");
            }
            $this->typeClass($TypeClassOrClasses);
        } else {
            throw new NotATypeException("Value for {$valueFor} {$argumentName} is not a type or a list of types.");
        }

        if ($callback) {
            $callback($this);
        }

        return $this;
    }

    public function typeClass(string $TypeClass): ActionResponse
    {
        $this->TypeClass = $TypeClass;

        return $this;
    }

    public function getTypeClass(): ?string
    {
        return $this->TypeClass ?? null;
    }

    public function getTypeInstance(string $typeName): Type
    {
        $TypeClass = array_values(array_filter(
            $this->getAllTypeClasses(),
            fn ($TypeClass) => $TypeClass::type() === $typeName
        ))[0];
        return $this->container->get($TypeClass);
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

    public function getAllTypeClasses(): array
    {
        if (isset($this->TypeClass)) {
            return [$this->TypeClass];
        }
        return $this->TypeClasses;
    }

    public function getAllTypeNames(): array
    {
        $typeClasses = $this->getAllTypeClasses();
        return array_map(fn ($Class) => $Class::type(), $typeClasses);
    }

    public function allowsType(string $typeName): bool
    {
        $allowedTypeNames = $this->getAllTypeNames();
        return in_array($typeName, $allowedTypeNames);
    }

    public function isList(): bool
    {
        return $this->list;
    }

    public function isLink(): bool
    {
        return $this->link;
    }

    public function isUnion(): bool
    {
        return count($this->getTypeClasses()) > 1;
    }

    public function toSchemaJson(): array
    {
        $json = [];

        if (isset($this->TypeClass)) {
            $json['type'] = $this->TypeClass::type();
        } else {
            $json['types'] = array_map(function ($TypeClass) {
                return $TypeClass::type();
            }, $this->TypeClasses);
        }

        if ($this->list) {
            $json['list'] = true;
        }

        if ($this->link) {
            $json['link'] = true;
        }

        return $json;
    }

    protected function getNameForException(): string
    {
        return 'response';
    }

    protected function getArgumentNameForException(): string
    {
        return '$TypeClassOrClasses';
    }
}
