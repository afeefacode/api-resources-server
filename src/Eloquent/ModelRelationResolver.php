<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Resolver\Field\RelationResolverTrait;
use Afeefa\ApiResources\Resolver\MutationRelationHasManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkOneResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

class ModelRelationResolver
{
    use RelationResolverTrait;

    public function get_relation(QueryRelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // select field on the owner prior loading the relation
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    $fields = [$eloquentRelation->getForeignKeyName()];
                    if ($eloquentRelation instanceof MorphTo) { // reference to the related in the owner table
                        $fields[] = $eloquentRelation->getMorphType();
                    }
                    return $fields;
                }
            })

            ->count(function () {
                // count is resolved using withCount() on the owner
            })

            ->get(function (array $owners) use ($r) {
                $relationWrapper = $this->getEloquentRelationWrapper($r->getRelation());
                $eloquentRelation = $relationWrapper->relation();

                // select field on the relation prior matching the related to its owner
                $selectFields = $r->getSelectFields();
                if ($eloquentRelation instanceof HasOneOrMany) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                    if ($eloquentRelation instanceof MorphOneOrMany) { // reference to the owner in the related table
                        $selectFields[] = $eloquentRelation->getMorphType();
                    }
                }

                $relationCounts = $this->getRelationCountsOfRelation($r);

                $params = $r->getParams();

                $builder = new Builder($relationWrapper->owner);
                $relatedModels = $builder->afeefaEagerLoadRelation($owners, $relationWrapper->name, $selectFields, $relationCounts, $params);

                return $relatedModels;
            });
    }

    public function save_has_one_relation(MutationRelationHasOneResolver $r)
    {
        $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();

        if ($eloquentRelation instanceof HasOne) { // reference to the owner in the related table
            $r
                ->saveOwnerToRelated(function (string $id, string $typeName) use ($eloquentRelation) {
                    return [$eloquentRelation->getForeignKeyName() => $id];
                });
        }

        if ($eloquentRelation instanceof BelongsTo) { // reference to the owner in the related table
            $r
                ->saveRelatedToOwner(function (?string $id) use ($eloquentRelation) {
                    return [$eloquentRelation->getForeignKeyName() => $id];
                })
                ->addBeforeOwner(function (string $typeName, array $saveFields) use ($r) {
                    $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                    $relatedModel = $eloquentRelation->getRelated();
                    if (!empty($saveFields)) {
                        $relatedModel->fillable(array_keys($saveFields));
                        $relatedModel->fill($saveFields);
                    }
                    $relatedModel->save();
                    return $relatedModel->fresh();
                });
        }

        $r
            ->get(function (Model $owner) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                return $eloquentRelation->get()->first();
            })
            ->add(function (Model $owner, string $typeName, array $saveFields) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                $relatedModel = $eloquentRelation->getRelated();
                if (!empty($saveFields)) {
                    $relatedModel->fillable(array_keys($saveFields));
                    $relatedModel->fill($saveFields);
                }
                $relatedModel->save();
                return $relatedModel->fresh();
            })
            ->update(function (Model $owner, Model $modelToUpdate, array $saveFields) use ($r) {
                if (!empty($saveFields)) {
                    $modelToUpdate->fillable(array_keys($saveFields));
                    $modelToUpdate->fill($saveFields);
                    $modelToUpdate->save();
                }
            })
            ->delete(function (Model $owner, Model $modelToDelete) use ($r) {
                $modelToDelete->delete();
            });
    }

    public function save_has_many_relation(MutationRelationHasManyResolver $r)
    {
        $r
            ->saveOwnerToRelated(function (string $id, string $typeName) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                if ($eloquentRelation instanceof HasMany) { // reference to the owner in the related table
                    return [$eloquentRelation->getForeignKeyName() => $id];
                }
            })
            ->get(function (Model $owner) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                return $eloquentRelation->get()->all();
            })
            ->add(function (Model $owner, string $typeName, array $saveFields) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                $relatedModel = $eloquentRelation->getRelated();
                if (!empty($saveFields)) {
                    $relatedModel->fillable(array_keys($saveFields));
                    $relatedModel->fill($saveFields);
                }
                $relatedModel->save();
                return $relatedModel->fresh();
            })
            ->update(function (Model $owner, Model $modelToUpdate, array $saveFields) use ($r) {
                if (!empty($saveFields)) {
                    $modelToUpdate->fillable(array_keys($saveFields));
                    $modelToUpdate->fill($saveFields);
                    $modelToUpdate->save();
                }
            })
            ->delete(function (Model $owner, Model $modelToDelete) use ($r) {
                $modelToDelete->delete();
            });
    }

    public function save_link_one_relation(MutationRelationLinkOneResolver $r)
    {
        $r
            ->saveRelatedToOwner(function (?string $id) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName() => $id];
                }
            });
    }

    public function save_link_many_relation(MutationRelationLinkManyResolver $r)
    {
    }

    protected function getRelationCountsOfRelation(QueryRelationResolver $r): array
    {
        $requestedFieldNames = $r->getRequestedFieldNames();
        $relatedType = $r->getRelation()->getRelatedType()->getTypeInstance();
        $relationCounts = [];
        foreach ($requestedFieldNames as $fieldName) {
            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($relatedType->hasRelation($countRelationName)) {
                    $isEloquentRelationResolver = $relatedType->getRelation($countRelationName)->getResolveParam('is_eloquent_relation');
                    if ($isEloquentRelationResolver) {
                        $relationCounts[] = $countRelationName . ' as count_' . $countRelationName;
                    }
                }
            }
        }
        return $relationCounts;
    }

    protected function getEloquentRelationWrapper(Relation $relation, Model $owner = null): EloquentRelationWrapper
    {
        $eloquentRelation = new EloquentRelationWrapper();

        $eloquentRelation->name = $relation->hasResolveParam('eloquent_relation')
            ? $relation->getResolveParam('eloquent_relation')
            : $relation->getName();

        if (!$owner) {
            /** @var ModelType */
            $ownerType = $relation->getOwner();
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

    public function relation(): EloquentRelation
    {
        return $this->owner->{$this->name}();
    }
}
