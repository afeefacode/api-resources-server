<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;
use Closure;

class RelationResolver extends DataResolver
{
    protected Relation $relation;

    protected Type $ownerType;

    /**
     * Closure or array
     */
    protected $ownerIdFields;

    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

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

    public function addOwner(ModelInterface $owner): RelationResolver
    {
        $this->owners[] = $owner;
        return $this;
    }

    /**
     * @return ModelInterface[]
     */
    public function getOwners(): array
    {
        return $this->owners;
    }

    public function resolve(): void
    {
    }
}
