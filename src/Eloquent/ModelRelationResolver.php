<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\DB\GetRelationResolver;
use Afeefa\ApiResources\DB\RelationRelatedData;
use Afeefa\ApiResources\DB\RelationResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Afeefa\ApiResources\DB\SaveRelationResolver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;

class ModelRelationResolver
{
    public function get_relation(GetRelationResolver $r)
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

    public function save_relation(SaveRelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // save fields on the owner in order to establish a new relation
                $eloquentRelation = $this->getEloquentRelation($r)[2];
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName()];
                }
            })

            ->set(function (Model $owner, array $relatedObjects) use ($r) {
                $eloquentRelation = $this->getEloquentRelation($r, $owner)[2];

                // remove all related models that are not part of the related objects
                $ids = array_filter(array_map(function ($relatedObject) {
                    return $relatedObject->id;
                }, $relatedObjects));

                $relatedModels = $eloquentRelation->whereNotIn('id', $ids)->get();
                foreach ($relatedModels as $relatedModel) {
                    $relatedModel->delete();
                }

                // add or update all others
                foreach ($relatedObjects as $relatedObject) {
                    if ($relatedObject->id) {
                        $this->addRelated($eloquentRelation, $relatedObject);
                    } else {
                        $this->updateRelated($eloquentRelation, $relatedObject);
                    }
                }
            })

            ->update(function (Model $owner, array $relatedObjects) use ($r) {
                $eloquentRelation = $this->getEloquentRelation($r, $owner)[2];

                foreach ($relatedObjects as $relatedObject) {
                    $this->updateRelated($eloquentRelation, $relatedObject);
                }
            })

            ->add(function (Model $owner, array $relatedObjects) use ($r) {
                $eloquentRelation = $this->getEloquentRelation($r, $owner)[2];

                foreach ($relatedObjects as $relatedObject) {
                    $this->addRelated($eloquentRelation, $relatedObject);
                }
            })

            ->delete(function (Model $owner, array $relatedObjects) use ($r) {
                $eloquentRelation = $this->getEloquentRelation($r, $owner)[2];

                foreach ($relatedObjects as $relatedObject) {
                    $relatedModel = $eloquentRelation->find($relatedObject->id);

                    if ($relatedModel) {
                        $relatedModel->delete();
                    }
                }
            });
    }

    protected function updateRelated(Relation $eloquentRelation, RelationRelatedData $relatedData): void
    {
        $relatedModel = $eloquentRelation->find($relatedData->id);

        if ($relatedModel) {
            $relatedModel->fillable(array_keys($relatedData->updates));
            $relatedModel->update($relatedData->updates);
        } else {
            $relatedData->saved = false;
        }
    }

    protected function addRelated(Relation $eloquentRelation, RelationRelatedData $relatedData): void
    {
        $owner = $eloquentRelation->getParent();
        $relatedModel = $eloquentRelation->getRelated();
        $updates = $relatedData->updates;

        // set foreign key if neccessary
        if ($eloquentRelation instanceof HasMany || $eloquentRelation instanceof HasOne) { // reference to the owner in the related table
                $foreignKey = $eloquentRelation->getForeignKeyName();
            $updates[$foreignKey] = $owner->id;
        }

        // check existance
        $uniqueFields = $relatedModel->getUniqueFields();
        $testFields = [];
        foreach ($uniqueFields as $uniqueField) {
            $testFields[$uniqueField] = $updates[$uniqueField];
        }
        if ($eloquentRelation->where($testFields)->exists()) {
            $relatedData->saved = false;
            return;
        }

        $relatedModel->fillable(array_keys($updates));
        $relatedModel->fill($updates);
        $relatedModel->save();
    }

    protected function getRelationCountsOfRelation(RelationResolver $r, ResolveContext $c): array
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

    protected function getEloquentRelation(RelationResolver $r, Model $owner = null): array
    {
        $relation = $r->getRelation();
        $eloquentRelationName = $relation->hasResolveParam('eloquent_relation')
            ? $relation->getResolveParam('eloquent_relation')
            : $relation->getName();

        if (!$owner) {
            /** @var ModelType */
            $ownerType = $r->getOwnerType();
            $OwnerClass = $ownerType::$ModelClass;
            $owner = new $OwnerClass();
        }

        $eloquentRelation = $owner->$eloquentRelationName();

        return [$owner, $eloquentRelationName, $eloquentRelation];
    }
}
