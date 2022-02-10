<?php

namespace Afeefa\ApiResources\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Builder extends EloquentBuilder
{
    public function __construct(Model $owner)
    {
        parent::__construct($owner::getQuery());

        $this->setModel($owner);
    }

    /**
     * This overrides the original protected function:
     * - to make it public
     * - to select custom fields instead of '*'
     * - to return the result set
     *
     * @see parent::eagerLoadRelation()
     */
    public function afeefaEagerLoadRelation(array $models, string $relationName, array $selectFields, array $relationCounts, array $params)
    {
        $relation = $this->getRelation($relationName);

        $relatedTable = $relation->getRelated()->getTable();
        $selectFields = array_map(function ($field) use ($relatedTable) {
            return $relatedTable . '.' . $field;
        }, $selectFields);

        $relation->addEagerConstraints($models);

        // select $selectFields before counts, since withCount()
        // will add a '*' column by default, which we don't want.
        $relation->select($selectFields);

        $limit = $params['limit'] ?? null;
        if ($limit) {
            $relation->limit($limit);
        }

        if (count($relationCounts)) {
            $relation->withCount($relationCounts);
        }

        $relatedModels = $relation->get();

        $ownersWithRelation = $relation->match(
            $relation->initRelation($models, $relationName),
            $relatedModels,
            $relationName
        );

        $result = [];

        foreach ($ownersWithRelation as $owner) {
            $item = $owner->$relationName;
            if ($item instanceof Collection) {
                $item = $item->all();
            } elseif (!$item) {
                continue;
            }
            $result[$owner->id] = $item;
        }

        return $result;
    }
}
