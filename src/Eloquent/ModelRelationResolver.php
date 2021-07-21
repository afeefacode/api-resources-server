<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\DB\GetRelationResolver;
use Afeefa\ApiResources\DB\RelationRelatedData;
use Afeefa\ApiResources\DB\RelationResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Afeefa\ApiResources\DB\SaveRelationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ModelRelationResolver
{
    public function get_relation(GetRelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // select field on the owner prior loading the relation
                $eloquentRelation = $this->getEloquentRelation($r)->relation();
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName()];
                }
            })

            ->load(function (array $owners, ResolveContext $c) use ($r) {
                $relationWrapper = $this->getEloquentRelation($r);
                $eloquentRelation = $relationWrapper->relation();

                // select field on the relation prior matching the related to its owner
                $selectFields = $c->getSelectFields();
                if ($eloquentRelation instanceof HasMany) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                }
                if ($eloquentRelation instanceof HasOne) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                }

                $relationCounts = $this->getRelationCountsOfRelation($r, $c);

                $builder = new Builder($relationWrapper->owner);
                $relatedModels = $builder->afeefaEagerLoadRelation($owners, $relationWrapper->name, $selectFields, $relationCounts);

                return $relatedModels->all();
            });
    }

    public function save_relation(SaveRelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // save fields on the owner in order to establish a new relation
                $eloquentRelation = $this->getEloquentRelation($r)->relation();
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName()];
                }
            })

            ->set(function (Model $owner, array $relatedObjects) use ($r) {
                $relationWrapper = $this->getEloquentRelation($r, $owner);

                // remove all related models that are not part of the related objects
                $ids = array_filter(array_map(function ($relatedObject) {
                    return $relatedObject->id;
                }, $relatedObjects));

                $relatedModels = $relationWrapper->relation()->whereNotIn('id', $ids)->get();
                foreach ($relatedModels as $relatedModel) {
                    $relatedModel->delete();
                }

                // add or update all others
                foreach ($relatedObjects as $relatedObject) {
                    if ($relatedObject->id) {
                        $this->updateRelated($relationWrapper, $relatedObject);
                    } else {
                        $this->addRelated($relationWrapper, $relatedObject);
                    }
                }
            })

            ->update(function (Model $owner, array $relatedObjects) use ($r) {
                $relationWrapper = $this->getEloquentRelation($r, $owner);
                foreach ($relatedObjects as $relatedObject) {
                    $this->updateRelated($relationWrapper, $relatedObject);
                }
            })

            ->add(function (Model $owner, array $relatedObjects) use ($r) {
                $relationWrapper = $this->getEloquentRelation($r, $owner);
                foreach ($relatedObjects as $relatedObject) {
                    $this->addRelated($relationWrapper, $relatedObject);
                }
            })

            ->delete(function (Model $owner, array $relatedObjects) use ($r) {
                $relationWrapper = $this->getEloquentRelation($r, $owner);
                foreach ($relatedObjects as $relatedObject) {
                    $relatedModel = $relationWrapper->relation()->find($relatedObject->id);
                    if ($relatedModel) {
                        $relatedModel->delete();
                    }
                }
            });
    }

    protected function updateRelated(EloquentRelationWrapper $relationWrapper, RelationRelatedData $relatedData): void
    {
        $eloquentRelation = $relationWrapper->relation();

        $relatedModel = $eloquentRelation->find($relatedData->id);

        if ($relatedModel) {
            $relatedModel->fillable(array_keys($relatedData->updates));
            $relatedModel->update($relatedData->updates);
        } else {
            $relatedData->saved = false;
        }
    }

    protected function addRelated(EloquentRelationWrapper $relationWrapper, RelationRelatedData $relatedData): void
    {
        $eloquentRelation = $relationWrapper->relation();

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
        if (count($uniqueFields)) {
            $testFields = [];
            foreach ($uniqueFields as $uniqueField) {
                $testFields[$uniqueField] = $updates[$uniqueField];
            }
            if ($eloquentRelation->where($testFields)->exists()) {
                $relatedData->saved = false;
                return;
            }
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

    protected function getEloquentRelation(RelationResolver $r, Model $owner = null): EloquentRelationWrapper
    {
        $eloquentRelation = new EloquentRelationWrapper();

        $relation = $r->getRelation();
        $eloquentRelation->name = $relation->hasResolveParam('eloquent_relation')
            ? $relation->getResolveParam('eloquent_relation')
            : $relation->getName();

        if (!$owner) {
            /** @var ModelType */
            $ownerType = $r->getOwnerType();
            $OwnerClass = $ownerType::$ModelClass;
            $owner = new $OwnerClass();
        }

        $eloquentRelation->owner = $owner;

        return $eloquentRelation;
    }
}

class EloquentRelationWrapper
{
    public Model $owner;
    public string $name;

    public function relation()
    {
        return $this->owner->{$this->name}();
    }
}
