<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Type\RelatedType;
use Closure;

/**
 * @method Relation owner($owner)
 * @method Relation name(string $name)
 * @method Relation validate(Closure $callback)
 * @method Relation validator(Validator $validator)
 * @method Relation required(bool $required = true)
 * @method Relation allowed(bool $allowed = true)
 * @method Relation resolve(string|callable|Closure $classOrCallback)
 * @method Relation resolveSave(string|callable|Closure $classOrCallback)
 * @method Relation resolveParam(string $key, $value)
 * @method Relation resolveParams(array $params)
 */
class Relation extends Field
{
    protected RelatedType $relatedType;

    protected bool $isUpdate = false;

    protected bool $isAdd = false;

    protected bool $isDelete = false;

    public function updatesItems(bool $updates = true): Relation
    {
        $this->isUpdate = $updates;
        return $this;
    }

    public function shallUpdateItems(): bool
    {
        return $this->isUpdate;
    }

    public function addsItems(bool $adds = true): Relation
    {
        $this->isAdd = $adds;
        return $this;
    }

    public function shallAddItems(): bool
    {
        return $this->isAdd;
    }

    public function deletesItems(bool $deletes = true): Relation
    {
        $this->isDelete = $deletes;
        return $this;
    }

    public function shallDeleteItems(): bool
    {
        return $this->isDelete;
    }

    public function isSingle(): bool
    {
        return !$this->isList();
    }

    public function isList(): bool
    {
        return $this->relatedType->isList();
    }

    public function isLink(): bool
    {
        return $this->relatedType->isLink();
    }

    public function typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta): Relation
    {
        $this->relatedType = $this->container->create(RelatedType::class)
            ->relationName($this->name)
            ->initFromArgument($TypeClassOrClassesOrMeta);
        return $this;
    }

    public function getRelatedType(): RelatedType
    {
        return $this->relatedType;
    }

    public function clone(): Relation
    {
        /** @var Relation */
        $relation = parent::clone();
        $relation->relatedType = $this->relatedType;
        return $relation;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        $json['related_type'] = $this->relatedType->toSchemaJson();

        return $json;
    }
}
