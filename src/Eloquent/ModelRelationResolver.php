<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\DB\RelationResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModelRelationResolver
{
    protected ModelType $type;
    protected string $ModelClass;
    protected string $relationName;

    public function type(ModelType $type): ModelRelationResolver
    {
        $this->type = $type;
        $this->ModelClass = $type::$ModelClass;
        return $this;
    }

    public function relationName(string $relationName): ModelRelationResolver
    {
        $this->relationName = $relationName;
        return $this;
    }

    public function relation(RelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // select field on the owner prior loading the relation
                $eloquentRelation = $this->getEloquentRelation($r)[2];
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName()];
                }
            })

            ->load(function (array $owners, ResolveContext $c) use ($r) {
                [$owner, $relationName, $eloquentRelation] = $this->getEloquentRelation($r);

                // select field on the relation prior matching the related to its owner
                $selectFields = $c->getSelectFields();
                if ($eloquentRelation instanceof HasMany) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                }
                if ($eloquentRelation instanceof HasOne) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                }

                $relationCounts = $this->getRelationCountsOfRelation($r, $c);

                $builder = new Builder($owner);
                $relatedModels = $builder->afeefaEagerLoadRelation($owners, $relationName, $selectFields, $relationCounts);

                return $relatedModels->all();
            });
    }

    private function getRelationCountsOfRelation(RelationResolver $r, ResolveContext $c): array
    {
        $requestedFieldNames = $c->getRequestedFields()->getFieldNames();
        $relatedType = $r->getRelation()->getRelatedTypeInstance();
        $relationCounts = [];
        foreach ($requestedFieldNames as $fieldName) {
            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($relatedType->hasRelation($countRelationName)) {
                    $relationCounts[] = $countRelationName . ' as count_' . $countRelationName;
                }
            }
        }
        return $relationCounts;
    }

    private function getEloquentRelation(RelationResolver $r): array
    {
        $relationName = $r->getRelation()->getName();

        /** @var ModelType */
        $ownerType = $r->getOwnerType();
        $OwnerClass = $ownerType::$ModelClass;
        $owner = new $OwnerClass();

        $eloquentRelation = $owner->$relationName();

        return [$owner, $relationName, $eloquentRelation];
    }
}
