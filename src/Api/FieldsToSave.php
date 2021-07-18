<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DB\TypeClassMap;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\Type;
use JsonSerializable;

class FieldsToSave implements ContainerAwareInterface, JsonSerializable
{
    use ContainerAwareTrait;

    protected Type $type;

    protected $operation = Operation::UPDATE;

    protected array $fields;

    protected ?string $id = null;

    public function typeClass(string $TypeClass): FieldsToSave
    {
        $this->type = $this->container->get($TypeClass);
        return $this;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function operation(string $operation): FieldsToSave
    {
        $this->operation = $operation;
        return $this;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function fields(array $fields): FieldsToSave
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

    public function getFields(): array
    {
        return $this->fields;
    }

    public function id(string $id): FieldsToSave
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @return Attribute[]
     */
    public function getAttributes(): array
    {
        $type = $this->type;
        $attributes = [];
        foreach ($this->getFieldNames() as $fieldName) {
            if ($type->hasAttribute($fieldName)) {
                $attributes[$fieldName] = $type->getAttribute($fieldName);
            }
        }
        return $attributes;
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

    /**
     * @return FieldsToSave|FieldsToSave[]
     */
    public function getNestedField($fieldName)
    {
        return $this->fields[$fieldName];
    }

    public function jsonSerialize()
    {
        return $this->fields;
    }

    /**
     * filter out all non allowed fields from request
     */
    protected function normalize(array $fields): array
    {
        $normalizedFields = [];

        foreach ($fields as $fieldName => $value) {
            if ($this->hasAttribute($fieldName)) {
                $normalizedFields[$fieldName] = $value;
            }

            if ($this->hasRelation($fieldName)) {
                $relation = $this->getRelation($fieldName);
                $TypeClass = $relation->getRelatedTypeClass();

                if ($relation->isSingle()) {
                    $normalizedFields[$fieldName] = $value ? $this->createNestedFields($TypeClass, $value) : null;
                } else {
                    foreach ($value as $index => $nestedSet) {
                        $normalizedFields[$fieldName][$index] = $this->createNestedFields($TypeClass, $nestedSet);
                    }
                }
            }
        }

        return $normalizedFields;
    }

    protected function getTypeClassByName(string $typeName = null): string
    {
        return $this->container->call(function (TypeClassMap $typeClassMap) use ($typeName) {
            return $typeClassMap->get($typeName);
        });
    }

    protected function createNestedFields(string $TypeClass, array $fields): FieldsToSave
    {
        return $this->container->create(function (FieldsToSave $fieldsToSave) use ($TypeClass, $fields) {
            $operation = isset($fields['id']) ? Operation::UPDATE : Operation::CREATE;

            $fieldsToSave
                ->typeClass($TypeClass)
                ->operation($operation)
                ->fields($fields);

            if ($operation === Operation::UPDATE) {
                $fieldsToSave->id($fields['id']);
            }
        });
    }

    protected function hasAttribute(string $name): bool
    {
        $method = $this->operation === Operation::UPDATE ? 'Update' : 'Create';
        return $this->type->{'has' . $method . 'Attribute'}($name);
    }

    protected function hasRelation(string $name): bool
    {
        $method = $this->operation === Operation::UPDATE ? 'Update' : 'Create';
        return $this->type->{'has' . $method . 'Relation'}($name);
    }

    protected function getRelation(string $name): Relation
    {
        $method = $this->operation === Operation::UPDATE ? 'Update' : 'Create';
        return $this->type->{'get' . $method . 'Relation'}($name);
    }
}
