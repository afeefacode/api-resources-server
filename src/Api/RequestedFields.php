<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Type\Type;
use JsonSerializable;

class RequestedFields implements ContainerAwareInterface, JsonSerializable
{
    use ContainerAwareTrait;

    protected Type $type;

    /**
     * @var array
     */
    protected array $fields;

    public function typeClass(string $TypeClass): RequestedFields
    {
        $this->type = $this->getTypeByClass($TypeClass);
        return $this;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function fields(array $fields): RequestedFields
    {
        $this->fields = $this->normalize($fields);
        return $this;
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    /**
     * @return Relation[]
     */
    public function getRelations(): array
    {
        $type = $this->type;
        $relations = [];
        foreach ($this->getFieldNames() as $fieldName) {
            if ($type->hasRelation($fieldName)) {
                $relations[$fieldName] = $type->getRelation($fieldName);
            }
        }
        return $relations;
    }

    public function getFieldNamesForType(Type $type): array
    {
        $fieldNames = [];
        foreach ($this->fields as $fieldName => $nested) {
            if (preg_match('/^\@(.+)/', $fieldName, $matches)) {
                if ($type::$type === $matches[1]) {
                    $fieldNames = array_merge($fieldNames, $nested->getFieldNames());
                }
            } else {
                $fieldNames[] = $fieldName;
            }
        }
        return $fieldNames;
    }

    public function getNestedField($fieldName): RequestedFields
    {
        return $this->fields[$fieldName];
    }

    public function jsonSerialize()
    {
        return $this->fields;
    }

    protected function normalize(array $fields): array
    {
        $type = $this->type;
        $normalizedFields = [];

        foreach ($fields as $fieldName => $nested) {
            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($type->hasRelation($countRelationName)) {
                    $normalizedFields[$fieldName] = true;
                }
            }

            if ($type->hasAttribute($fieldName)) {
                $normalizedFields[$fieldName] = true;
            }

            if ($type->hasRelation($fieldName)) {
                if ($nested === true) {
                    $nested = [];
                }
                if (is_array($nested)) {
                    $TypeClass = $type->getRelation($fieldName)->getRelatedTypeClass();
                    $normalizedFields[$fieldName] = $this->createNestedFields($TypeClass, $nested);
                }
            }

            if (preg_match('/^\@(.+)/', $fieldName, $matches)) {
                if ($nested === true) {
                    $nested = [];
                }
                if (is_array($nested)) {
                    $TypeClass = $this->getTypeClassByName($matches[1]);
                    $normalizedFields[$fieldName] = $this->createNestedFields($TypeClass, $nested);
                }
            }
        }

        return $normalizedFields;
    }

    protected function getTypeByClass(string $TypeClass = null): Type
    {
        $TypeClass = $TypeClass ?: $this->TypeClass;
        return $this->container->get($TypeClass);
    }

    protected function getTypeClassByName(string $typeName = null): string
    {
        return $this->container->call(function (TypeClassMap $typeClassMap) use ($typeName) {
            return $typeClassMap->getClass($typeName);
        });
    }

    protected function createNestedFields(string $TypeClass, array $fields): RequestedFields
    {
        return $this->container->create(function (RequestedFields $requestedFields) use ($TypeClass, $fields) {
            $requestedFields
                ->typeClass($TypeClass)
                ->fields($fields);
        });
    }
}
