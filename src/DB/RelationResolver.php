<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;

class RelationResolver extends DataResolver
{
    protected Relation $relation;

    protected RequestedFields $requestedFields;

    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

    protected array $ownerIdFields = [];

    protected ?Closure $loadCallback = null;

    protected ?Closure $mapCallback = null;

    public function relation(Relation $relation): RelationResolver
    {
        $this->relation = $relation;
        return $this;
    }

    public function requestedFields(RequestedFields $fields): RelationResolver
    {
        $this->requestedFields = $fields;
        return $this;
    }

    public function ownerIdFields(array $ownerIdFields): RelationResolver
    {
        $this->ownerIdFields = $ownerIdFields;
        return $this;
    }

    public function getOwnerIdFields(): array
    {
        return $this->ownerIdFields;
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

    public function fetch()
    {
        $requestedFields = $this->requestedFields;

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields);

        // query db

        $loadCallback = $this->loadCallback;
        $objects = $loadCallback($this->owners, $resolveContext);

        // map results to owners

        $mapCallback = $this->mapCallback;
        $relationName = $this->relation->getName();

        foreach ($this->owners as $owner) {
            $value = $mapCallback($objects, $owner);
            $owner->apiResourcesSetRelation($relationName, $value);
        }

        // no objects -> no relations
        if (!count($objects)) {
            return;
        }

        // resolve sub relations

        if ($this->relation->isSingle()) {
            $models = array_values($objects);
        } else {
            $models = array_merge(...array_values($objects)); // flatten
        }

        foreach ($resolveContext->getRelationResolvers() as $relationResolver) {
            foreach ($models as $model) {
                $relationResolver->addOwner($model);
            }
            $relationResolver->fetch();
        }
    }
}
