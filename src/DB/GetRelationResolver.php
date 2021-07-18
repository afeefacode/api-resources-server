<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Closure;

/**
 * @method GetRelationResolver ownerIdFields($ownerIdFields)
 * @method GetRelationResolver addOwner(ModelInterface $owner)
 */
class GetRelationResolver extends RelationResolver
{
    protected RequestedFields $requestedFields;

    protected ?Closure $initCallback = null;

    protected ?Closure $loadCallback = null;

    protected ?Closure $mapCallback = null;

    protected ?Closure $flattenCallback = null;

    public function requestedFields(RequestedFields $fields): RelationResolver
    {
        $this->requestedFields = $fields;
        return $this;
    }

    public function init(Closure $callback): RelationResolver
    {
        $this->initCallback = $callback;
        return $this;
    }

    public function load(Closure $callback): RelationResolver
    {
        $this->loadCallback = $callback;
        return $this;
    }

    public function map(Closure $callback): RelationResolver
    {
        $this->mapCallback = $callback;
        return $this;
    }

    public function flatten(Closure $callback): RelationResolver
    {
        $this->flattenCallback = $callback;
        return $this;
    }

    public function resolve(): void
    {
        $requestedFields = $this->requestedFields;

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields);

        // init

        if (isset($this->initCallback)) {
            ($this->initCallback)();
        }

        // query db

        $loadCallback = $this->loadCallback;
        if (!$loadCallback) {
            throw new MissingCallbackException('resolve callback needs to implement a load() method.');
        }
        $objects = $loadCallback($this->owners, $resolveContext);

        if (!is_iterable($objects) || !is_countable($objects)) {
            throw new InvalidConfigurationException('load() method of a relation resolver must return iterable+countable objects.');
        }

        // map results to owners

        if (isset($this->mapCallback)) {
            $mapCallback = $this->mapCallback;
            $relationName = $this->relation->getName();

            foreach ($this->owners as $owner) {
                $value = $mapCallback($objects, $owner);
                $owner->apiResourcesSetRelation($relationName, $value);
            }
        }

        // no objects -> no relations to resolve

        if (!count($objects)) {
            return;
        }

        // resolve attributes and sub relations

        if (isset($this->flattenCallback)) {
            $models = ($this->flattenCallback)($objects);
        } else {
            $models = array_values($objects);
            // nested array
            if (is_array($models[0] ?? null)) {
                $models = array_merge(...$models);
            }
        }

        foreach ($resolveContext->getAttributeResolvers() as $attributeResolver) {
            foreach ($models as $model) {
                $attributeResolver->addOwner($model);
            }
            $attributeResolver->resolve();
        }

        foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
            foreach ($models as $model) {
                $relationResolver->addOwner($model);
            }
            $relationResolver->resolve();
        }
    }
}
