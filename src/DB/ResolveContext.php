<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\FieldsToSave;
use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\Type;

class ResolveContext implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected RequestedFields $requestedFields;

    /**
     * @var FieldsToSave|FieldsToSave[]
     */
    protected $fieldsToSave;

    /**
     * @var AttributeResolver[]
     */
    protected array $attributeResolvers;

    /**
     * @var RelationResolver[]
     */
    protected array $relationResolvers;

    /**
     * @var SaveRelationResolver[]
     */
    protected array $saveRelationResolvers;

    protected array $meta = [];

    public function requestedFields(RequestedFields $requestedFields): ResolveContext
    {
        $this->requestedFields = $requestedFields;
        return $this;
    }

    public function getRequestedFields(): RequestedFields
    {
        return $this->requestedFields;
    }

    /**
     * @param FieldsToSave|FieldsToSave[] $fieldsToSave
     */
    public function fieldsToSave($fieldsToSave): ResolveContext
    {
        $this->fieldsToSave = $fieldsToSave;
        return $this;
    }

    /**
     * @return FieldsToSave|FieldsToSave[]
     */
    public function getFieldsToSave()
    {
        return $this->fieldsToSave;
    }

    public function meta(array $meta): ResolveContext
    {
        $this->meta = $meta;
        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getSelectFields(string $typeName = null): array
    {
        if (!isset($this->relationResolvers)) {
            $this->createRelationResolvers();
        }

        $requestedFields = $this->requestedFields;
        $type = $typeName ? $this->getTypeByName($typeName) : $requestedFields->getType();

        return $this->calculateSelectFields($type, $requestedFields);
    }

    public function getSaveFields(): array
    {
        if (!isset($this->saveRelationResolvers)) {
            $this->createSaveRelationResolvers();
        }

        return $this->calculateSaveFields($this->fieldsToSave);
    }

    /**
     * @return AttributeResolver[]
     */
    public function getAttributeResolvers(): array
    {
        if (!isset($this->attributeResolvers)) {
            $this->createAttributeResolvers();
        }

        return $this->attributeResolvers;
    }

    /**
     * @return RelationResolver[]
     */
    public function getRelationResolvers(): array
    {
        if (!isset($this->relationResolvers)) {
            $this->createRelationResolvers();
        }

        return $this->relationResolvers;
    }

    /**
     * @return SaveRelationResolver[]
     */
    public function getSaveRelationResolvers(): array
    {
        if (!isset($this->saveRelationResolvers)) {
            $this->createSaveRelationResolvers();
        }

        return $this->saveRelationResolvers;
    }

    protected function calculateSelectFields(Type $type, RequestedFields $requestedFields): array
    {
        $relationResolvers = $this->relationResolvers;

        $selectFields = ['id'];

        foreach ($requestedFields->getFieldNames() as $fieldName) {
            if ($type->hasAttribute($fieldName)) {
                $attribute = $type->getAttribute($fieldName);
                if ($attribute->hasDependingAttributes()) {
                    $selectFields = array_merge($selectFields, $attribute->getDependingAttributes());
                } else {
                    if (!$attribute->hasResolver()) { // let resolvers provide value
                        $selectFields[] = $fieldName;
                    }
                }
            }

            if ($type->hasRelation($fieldName)) {
                $relationResolver = $relationResolvers[$fieldName];
                $selectFields = array_unique(
                    array_merge(
                        $selectFields,
                        $relationResolver->getOwnerIdFields()
                    )
                );
            }

            if (preg_match('/^\@(.+)/', $fieldName, $matches)) {
                $onTypeName = $matches[1];
                if ($type::$type === $onTypeName) {
                    $selectFields = array_unique(
                        array_merge(
                            $selectFields,
                            $this->calculateSelectFields($type, $requestedFields->getNestedField($fieldName))
                        )
                    );
                }
            }
        }

        return $selectFields;
    }

    protected function calculateSaveFields(FieldsToSave $fieldsToSave): array
    {
        $type = $fieldsToSave->getType();
        $saveRelationResolvers = $this->saveRelationResolvers;

        $saveFields = [];

        foreach ($fieldsToSave->getFields() as $fieldName => $value) {
            // value is a scalar
            if ($this->hasSaveAttribute($type, $fieldsToSave->getOperation(), $fieldName)) {
                $attribute = $type->getAttribute($fieldName);
                if (!$attribute->hasResolver()) { // let resolvers provide value
                    $saveFields[$fieldName] = $value;
                }
            }

            // value is a FieldsToSave or null
            if ($this->hasSaveRelation($type, $fieldsToSave->getOperation(), $fieldName)) {
                $relation = $this->getSaveRelation($type, $fieldsToSave->getOperation(), $fieldName);

                $saveRelationResolver = $saveRelationResolvers[$fieldName];
                $ownerIdFields = $saveRelationResolver->getOwnerIdFields();
                foreach ($ownerIdFields as $ownerIdField) {
                    if ($relation->isSingle() && !$value) {
                        $saveFields[$ownerIdField] = null;
                    } else {
                        // TODO set type field as id field if necessary
                        $saveFields[$ownerIdField] = $value->getId();
                    }
                }
            }
        }

        return $saveFields;
    }

    protected function getTypeByName(string $typeName): Type
    {
        return $this->container->call(function (TypeClassMap $typeClassMap) use ($typeName) {
            $TypeClass = $typeClassMap->get($typeName) ?? Type::class;
            return $this->container->get($TypeClass);
        });
    }

    /**
     * @return RelationResolver[]
     */
    protected function createRelationResolvers()
    {
        $requestedFields = $this->requestedFields;
        $type = $requestedFields->getType();

        $relationResolvers = [];
        foreach ($requestedFields->getFieldNames() as $fieldName) {
            if ($type->hasRelation($fieldName)) {
                $relation = $type->getRelation($fieldName);
                $resolveCallback = $relation->getResolve();

                /** @var GetRelationResolver */
                $relationResolver = null;

                if ($resolveCallback) {
                    $this->container->call(
                        $resolveCallback,
                        function (DependencyResolver $r) {
                            if ($r->isOf(GetRelationResolver::class)) {
                                $r->create();
                            }
                        },
                        function () use (&$relationResolver) {
                            $arguments = func_get_args();
                            foreach ($arguments as $argument) {
                                if ($argument instanceof GetRelationResolver) {
                                    $relationResolver = $argument;
                                }
                            }
                        }
                    );

                    if (!$relationResolver) {
                        throw new InvalidConfigurationException("Resolve callback for relation {$fieldName} on type {$type::$type} must receive a RelationResolver as argument.");
                    }

                    $relationResolver->ownerType($type);
                    $relationResolver->relation($relation);
                    $relationResolver->requestedFields($requestedFields->getNestedField($fieldName));
                    $relationResolvers[$fieldName] = $relationResolver;
                } else {
                    throw new InvalidConfigurationException("Relation {$fieldName} on type {$type::$type} does not have a relation resolver.");
                }
            }
        }

        $this->relationResolvers = $relationResolvers;
    }

    /**
     * @return SaveRelationResolver[]
     */
    protected function createSaveRelationResolvers()
    {
        $fieldsToSave = $this->fieldsToSave;
        $type = $fieldsToSave->getType();
        $operation = $fieldsToSave->getOperation();

        $saveRelationResolvers = [];
        foreach ($fieldsToSave->getFieldNames() as $fieldName) {
            if ($this->hasSaveRelation($type, $operation, $fieldName)) {
                $relation = $this->getSaveRelation($type, $operation, $fieldName);
                $resolveCallback = $relation->getSaveResolve();

                /** @var SaveRelationResolver */
                $saveRelationResolver = null;

                if ($resolveCallback) {
                    $this->container->call(
                        $resolveCallback,
                        function (DependencyResolver $r) {
                            if ($r->isOf(SaveRelationResolver::class)) {
                                $r->create();
                            }
                        },
                        function () use (&$saveRelationResolver) {
                            $arguments = func_get_args();
                            foreach ($arguments as $argument) {
                                if ($argument instanceof SaveRelationResolver) {
                                    $saveRelationResolver = $argument;
                                }
                            }
                        }
                    );

                    if (!$saveRelationResolver) {
                        throw new InvalidConfigurationException("Resolve callback for save relation {$fieldName} on type {$type::$type} must receive a SaveRelationResolver as argument.");
                    }

                    $saveRelationResolver->ownerType($type);
                    $saveRelationResolver->relation($relation);
                    $saveRelationResolver->fieldsToSave($fieldsToSave->getNestedField($fieldName));
                    $saveRelationResolvers[$fieldName] = $saveRelationResolver;
                } else {
                    throw new InvalidConfigurationException("Relation {$fieldName} on type {$type::$type} does not have a save relation resolver.");
                }
            }
        }

        $this->saveRelationResolvers = $saveRelationResolvers;
    }

    /**
     * @return AttributeResolver[]
     */
    protected function createAttributeResolvers()
    {
        $requestedFields = $this->requestedFields;
        $type = $requestedFields->getType();

        $attributeResolvers = [];
        foreach ($requestedFields->getFieldNames() as $fieldName) {
            if ($type->hasAttribute($fieldName)) {
                $attribute = $type->getAttribute($fieldName);
                if ($attribute->hasResolver()) {
                    $resolveCallback = $attribute->getResolve();
                    /** @var AttributeResolver */
                    $attributeResolver = null;

                    $this->container->call(
                        $resolveCallback,
                        function (DependencyResolver $r) {
                            if ($r->isOf(AttributeResolver::class)) {
                                $r->create();
                            }
                        },
                        function () use (&$attributeResolver) {
                            $arguments = func_get_args();
                            foreach ($arguments as $argument) {
                                if ($argument instanceof AttributeResolver) {
                                    $attributeResolver = $argument;
                                }
                            }
                        }
                    );

                    if (!$attributeResolver) {
                        throw new InvalidConfigurationException("Resolve callback for attribute {$fieldName} on type {$type::$type} must receive a AttributeResolver as argument.");
                    }

                    // $attributeResolver->ownerType($type);
                    $attributeResolver->attribute($attribute);
                    // $attributeResolver->requestedFields($requestedFields->getNestedField($fieldName));
                    $attributeResolvers[$fieldName] = $attributeResolver;
                }
            }
        }

        $this->attributeResolvers = $attributeResolvers;
    }

    protected function hasSaveAttribute(Type $type, string $operation, string $name): bool
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'has' . $method . 'Attribute'}($name);
    }

    protected function hasSaveRelation(Type $type, string $operation, string $name): bool
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'has' . $method . 'Relation'}($name);
    }

    protected function getSaveRelation(Type $type, string $operation, string $name): Relation
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'get' . $method . 'Relation'}($name);
    }
}
