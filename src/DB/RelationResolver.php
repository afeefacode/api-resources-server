<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
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
            // $models = array_values($objects);
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
