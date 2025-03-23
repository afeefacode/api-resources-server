<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Resolver\MutationRelationHasManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationHasOneResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkManyResolver;
use Afeefa\ApiResources\Resolver\MutationRelationLinkOneResolver;
use Afeefa\ApiResources\Resolver\QueryRelationResolver;
use Ankurk91\Eloquent\Relations\BelongsToOne;
use Ankurk91\Eloquent\Relations\MorphToOne;
use Error;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;

class ModelRelationResolver
{
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

                $typeNames = $r->getRelation()->getRelatedType()->getAllTypeNames();

                // if there are more than 1 allowed type, we must have a polymorphic morphTo relation
                // and need to distinguish between the diffent possible related types
                if (count($typeNames) > 1 && $eloquentRelation instanceof MorphTo) {
                    $ownersByRelatedType = $this->sortOwnersByRelatedType($owners, $eloquentRelation->getMorphType());
                } else {
                    $ownersByRelatedType = [$typeNames[0] => $owners];
                }

                $relatedModels = [];

                foreach ($typeNames as $typeName) {
                    if (!array_key_exists($typeName, $ownersByRelatedType)) { // type is allowed but no owner of that type found
                        continue;
                    }
                    $ownersOfRelatedType = $ownersByRelatedType[$typeName];

                    // select field on the relation prior matching the related to its owner
                    $selectFields = $r->getSelectFields($typeName);

                    if ($eloquentRelation instanceof HasOneOrMany) { // reference to the owner in the related table
                        $selectFields[] = $eloquentRelation->getForeignKeyName();
                        if ($eloquentRelation instanceof MorphOneOrMany) { // polymorphic type
                            $selectFields[] = $eloquentRelation->getMorphType();
                        }
                    }

                    $relationCounts = $this->getRelationCountsOfRelation($r, $typeName);

                    $builder = new Builder($relationWrapper->owner);

                    $relatedModels = [
                        ...$relatedModels,
                        ...$builder->afeefaEagerLoadRelation(
                            $ownersOfRelatedType,
                            $relationWrapper->name,
                            $selectFields,
                            $relationCounts,
                            $r->getParams()
                        )
                    ];
                }

                return $relatedModels;
            });
    }

    public function save_has_one_relation(MutationRelationHasOneResolver $r)
    {
        $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();

        if ($eloquentRelation instanceof HasOneOrMany) { // reference to the owner in the related table
            $r
                ->saveOwnerToRelated(function (string $id, string $typeName) use ($eloquentRelation) {
                    $ownerFields = [$eloquentRelation->getForeignKeyName() => $id]; // owner_id
                    if ($eloquentRelation instanceof MorphOneOrMany) {
                        $ownerFields[$eloquentRelation->getMorphType()] = $typeName; // owner_type
                    }
                    return $ownerFields;
                });
        }

        if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
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

                if ($eloquentRelation instanceof HasOneOrMany) { // reference to the owner in the related table
                    $ownerFields = [$eloquentRelation->getForeignKeyName() => $id]; // owner_id
                    if ($eloquentRelation instanceof MorphOneOrMany) {
                        $ownerFields[$eloquentRelation->getMorphType()] = $typeName; // owner_type
                    }
                    return $ownerFields;
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
        $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();

        if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
            $r
                ->saveRelatedToOwner(function (?string $id, ?string $typeName) use ($r) {
                    $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                    if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                        $fields = [$eloquentRelation->getForeignKeyName() => $id];
                        if ($eloquentRelation instanceof MorphTo) { // reference to the related in the owner table
                            $fields[$eloquentRelation->getMorphType()] = $typeName;
                        }
                        return $fields;
                    }
                });
        }

        $r
            ->get(function (Model $owner) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                return $eloquentRelation->first();
            })
            ->exists(function (string $id, string $typeName) use ($r) {
                $related = EloquentRelation::getMorphedModel($typeName);
                return !!$related::find($id);
            })
            ->link(function (Model $owner, string $id, string $typeName) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                $relatedModel = $eloquentRelation->getRelated()::find($id);
                $eloquentRelation->save($relatedModel); // MorphToOne + HasOne
            })
            ->unlink(function (Model $owner, Model $modelToUnlink) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();

                if ($eloquentRelation instanceof MorphToOne || $eloquentRelation instanceof BelongsToOne) {
                    $eloquentRelation->detach($modelToUnlink);
                } elseif ($eloquentRelation instanceof HasOne) {
                    $modelToUnlink[$eloquentRelation->getForeignKeyName()] = null;
                    $modelToUnlink->save();
                } else {
                    throw new Error('Not implemented.');
                }
            });
    }

    public function save_link_many_relation(MutationRelationLinkManyResolver $r)
    {
        $r
            ->get(function (Model $owner) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                return $eloquentRelation->get()->all();
            })
            ->exists(function (string $id, string $typeName) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation())->relation();
                return !!$eloquentRelation->getRelated()::find($id);
            })
            ->link(function (Model $owner, string $id, string $typeName) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                $relatedModel = $eloquentRelation->getRelated()::find($id);
                $eloquentRelation->attach($relatedModel);
            })
            ->unlink(function (Model $owner, Model $modelToUnlink) use ($r) {
                $eloquentRelation = $this->getEloquentRelationWrapper($r->getRelation(), $owner)->relation();
                $eloquentRelation->detach($modelToUnlink);
            });
    }

    protected function getRelationCountsOfRelation(QueryRelationResolver $r, string $typeName): array
    {
        $requestedFieldNames = $r->getRequestedFieldNames($typeName);
        $relatedType = $r->getRelation()->getRelatedType()->getTypeInstance($typeName);
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

    /**
     * @param ModelInterface[] $owners
     */
    protected function sortOwnersByRelatedType(array $owners, string $typeField): array
    {
        $ownersByRelatedType = [];
        foreach ($owners as $owner) {
            $typeInDb = $owner->$typeField;
            // handle legacy type
            $RelatedModel = EloquentRelation::getMorphedModel($typeInDb);
            $type = $RelatedModel::$type;
            $ownersByRelatedType[$type][] = $owner;
        }
        return $ownersByRelatedType;
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
