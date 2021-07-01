<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Type\Type;

class ResolveContext implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    // protected Type $type;

    protected RequestedFields $requestedFields;

    /**
     * @var AttributeResolver[]
     */
    protected array $attributeResolvers;

    /**
     * @var RelationResolver[]
     */
    protected array $relationResolvers;

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

                /** @var RelationResolver */
                $relationResolver = null;

                if ($resolveCallback) {
                    $this->container->call(
                        $resolveCallback,
                        function (DependencyResolver $r) {
                            if ($r->isOf(RelationResolver::class)) {
                                $r->create();
                            }
                        },
                        function () use (&$relationResolver) {
                            $arguments = func_get_args();
                            foreach ($arguments as $argument) {
                                if ($argument instanceof RelationResolver) {
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
}
