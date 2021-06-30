<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;
use Closure;

class RelationResolver extends DataResolver
{
    protected Relation $relation;

    protected RequestedFields $requestedFields;

    protected Type $ownerType;

    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

    /**
     * Closure or array
     */
    protected $ownerIdFields;

    protected ?Closure $initCallback = null;

    protected ?Closure $loadCallback = null;

    protected ?Closure $mapCallback = null;

    protected ?Closure $flattenCallback = null;

    public function ownerType(Type $ownerType): RelationResolver
    {
        $this->ownerType = $ownerType;
        return $this;
    }

    public function getOwnerType(): Type
    {
        return $this->ownerType;
    }

    public function relation(Relation $relation): RelationResolver
    {
        $this->relation = $relation;
        return $this;
    }

    public function getRelation(): Relation
    {
        return $this->relation;
    }

    public function requestedFields(RequestedFields $fields): RelationResolver
    {
        $this->requestedFields = $fields;
        return $this;
    }

    public function ownerIdFields($ownerIdFields): RelationResolver
    {
        $this->ownerIdFields = $ownerIdFields;
        return $this;
    }

    public function getOwnerIdFields(): array
    {
        if ($this->ownerIdFields instanceof Closure) {
            return ($this->ownerIdFields)() ?? [];
        }

        return $this->ownerIdFields ?? [];
    }

    public function addOwner(ModelInterface $owner): void
    {
        $this->owners[] = $owner;
    }

    /**
     * @return ModelInterface[]
     */
    public function getOwners(): array
    {
        return $this->owners;
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

    public function fetch()
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

        // resolve sub relations

        if (isset($this->flattenCallback)) {
            $models = ($this->flattenCallback)($objects);
        } else {
            $models = array_values($objects);
            // nested array
            if (is_array($models[0] ?? null)) {
                $models = array_merge(...$models);
            }
        }

        foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
            foreach ($models as $model) {
                $relationResolver->addOwner($model);
            }
            $relationResolver->fetch();
        }
    }
}
