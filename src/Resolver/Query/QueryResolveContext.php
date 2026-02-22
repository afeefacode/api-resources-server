<?php

namespace Afeefa\ApiResources\Resolver\Query;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackArgumentException;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Resolver\QueryAttributeResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use function Afeefa\ApiResources\DI\getCallbackArgumentType;
use Afeefa\ApiResources\Type\Type;

class QueryResolveContext implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected Type $type;

    protected array $fields = [];

    protected ?array $requestedFields = null;

    protected ?array $selectFields = null;

    /**
     * @var QueryAttributeResolver[]
     */
    protected ?array $attributeResolvers = null;

    /**
     * @var QueryRelationResolver[]
     */
    protected ?array $relationResolvers = null;

    /**
     * @var QueryRelationResolver[]
     */
    protected ?array $relationCountResolvers = null;

    public function type(Type $type): QueryResolveContext
    {
        $this->type = $type;
        return $this;
    }

    public function fields(array $fields): QueryResolveContext
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @return QueryAttributeResolver[]
     */
    public function getAttributeResolvers(): array
    {
        if (!$this->attributeResolvers) {
            $this->attributeResolvers = $this->createAttributeResolvers();
        }
        return $this->attributeResolvers;
    }

    /**
     * @return QueryRelationResolver[]
     */
    public function getRelationResolvers(): array
    {
        if (!$this->relationResolvers) {
            $this->relationResolvers = $this->createRelationResolvers();
        }
        return $this->relationResolvers;
    }

    /**
     * @return QueryRelationResolver[]
     */
    public function getRelationCountResolvers(): array
    {
        if (!$this->relationCountResolvers) {
            $this->relationCountResolvers = $this->createRelationResolvers(true);
        }
        return $this->relationCountResolvers;
    }

    public function getRequestedFields(): array
    {
        if (!$this->requestedFields) {
            $this->requestedFields = $this->calculateRequestedFields();
        }
        return $this->requestedFields;
    }

    public function getSelectFields(): array
    {
        if (!$this->selectFields) {
            $this->selectFields = $this->calculateSelectFields();
        }
        return $this->selectFields;
    }

    protected function createAttributeResolvers(): array
    {
        $type = $this->type;

        $attributeResolvers = [];

        $requestedFields = $this->getRequestedFields();

        foreach ($requestedFields as $fieldName => $value) {
            if ($type->hasAttribute($fieldName)) {
                $attribute = $type->getAttribute($fieldName);
                if ($attribute->hasResolver()) {
                    $resolveCallback = $attribute->getResolve();

                    /** @var QueryAttributeResolver */
                    $attributeResolver = null;
                    try {
                        $attributeResolver = $this->container->create(getCallbackArgumentType($resolveCallback));
                        $resolveCallback($attributeResolver);
                    } catch (MissingCallbackArgumentException) {
                    }

                    if (!($attributeResolver instanceof QueryAttributeResolver)) {
                        throw new InvalidConfigurationException("Resolve callback for attribute {$fieldName} on type {$type::type()} must receive a QueryAttributeResolver as argument.");
                    }

                    $attributeResolver->attribute($attribute);
                    $attributeResolvers[$fieldName] = $attributeResolver;
                }
            }
        }

        return $attributeResolvers;
    }

    protected function createRelationResolvers(bool $forCounts = false): array
    {
        $type = $this->type;

        $relationResolvers = [];

        $requestedFields = $this->getRequestedFields();

        foreach ($requestedFields as $fieldName => $value) {
            if ($forCounts) {
                if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                    $fieldName = $matches[1];
                } else {
                    continue;
                }
            }

            if ($type->hasRelation($fieldName)) {
                $relation = $type->getRelation($fieldName);

                if ($forCounts && $relation->isRestrictedTo(Relation::RESTRICT_TO_GET)) {
                    continue;
                }

                if (!$forCounts && $relation->isRestrictedTo(Relation::RESTRICT_TO_COUNT)) {
                    continue;
                }

                $resolveCallback = $relation->getResolve();

                if ($resolveCallback) {
                    /** @var QueryRelationResolver */
                    $relationResolver = null;
                    try {
                        $relationResolver = $this->container->create(getCallbackArgumentType($resolveCallback));
                        $resolveCallback($relationResolver);
                    } catch (MissingCallbackArgumentException) {
                    }

                    if (!($relationResolver instanceof QueryRelationResolver)) {
                        throw new InvalidConfigurationException("Resolve callback for relation {$fieldName} on type {$type::type()} must receive a QueryRelationResolver as argument.");
                    }

                    $relationResolver
                        ->relation($relation)
                        ->fields($forCounts ? [] : $value);
                    $relationResolvers[$fieldName] = $relationResolver;
                } else {
                    throw new InvalidConfigurationException("Relation {$fieldName} on type {$type::type()} does not have a relation resolver.");
                }
            }
        }

        return $relationResolvers;
    }

    protected function calculateSelectFields(): array
    {
        $type = $this->type;

        $attributeResolvers = $this->getAttributeResolvers();
        $relationResolvers = $this->getRelationResolvers();

        $selectFields = ['id']; // TODO this might be a problem if using no 'id' tables

        $requestedFields = $this->getRequestedFields();

        foreach ($requestedFields as $fieldName => $value) {
            // select attributes
            if ($type->hasAttribute($fieldName)) {
                $attribute = $type->getAttribute($fieldName);

                if ($attribute->hasResolver()) { // if a resolver
                    $attributeResolver = $attributeResolvers[$fieldName];
                    $attributeSelectFields = $attributeResolver->getSelectFields();
                    if (count($attributeSelectFields)) {
                        $selectFields = [...$selectFields, ...$attributeSelectFields];
                    }
                } else {
                    $selectFields[] = $fieldName; // default is just the attribute name
                }
            }

            // select relations
            if ($type->hasRelation($fieldName)) {
                $relationResolver = $relationResolvers[$fieldName];
                $selectFields = [
                    ...$selectFields,
                    ...$relationResolver->getOwnerIdFields()
                ];
            }
        }

        return array_unique($selectFields);
    }

    protected function calculateRequestedFields(?array $fields = null): array
    {
        $type = $this->type;
        $fields ??= $this->fields;

        $requestedFields = [];

        foreach ($fields as $fieldName => $nested) {
            // count_relation
            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($type->hasRelation($countRelationName)) {
                    $relation = $type->getRelation($countRelationName);
                    if (!$relation->isRestrictedTo(Relation::RESTRICT_TO_GET)) {
                        $requestedFields[$fieldName] = true;
                    }
                }
            }

            // attribute
            if ($type->hasAttribute($fieldName) && $nested === true) {
                $requestedFields[$fieldName] = true;
            }

            // relation = true or [...]
            if ($type->hasRelation($fieldName)) {
                if ($nested === true) {
                    $nested = [];
                }
                if (is_array($nested)) {
                    $relation = $type->getRelation($fieldName);
                    if (!$relation->isRestrictedTo(Relation::RESTRICT_TO_COUNT)) {
                        $requestedFields[$fieldName] = $nested;
                    }
                }
            }

            // on type fields
            if (preg_match('/^\@(.+)/', $fieldName, $matches)) {
                $onTypeName = $matches[1];
                if ($type::type() === $onTypeName) {
                    if ($nested === true) {
                        $nested = [];
                    }
                    if (is_array($nested)) {
                        $requestedFields = array_merge(
                            $requestedFields,
                            $this->calculateRequestedFields($nested)
                        );
                    }
                }
            }
        }

        return $requestedFields;
    }
}
