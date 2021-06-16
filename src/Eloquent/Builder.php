<?php

namespace Afeefa\ApiResources\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
    public function afeefaEagerLoadRelation(array $models, string $name, array $selectFields, array $relationCounts)
    {
        $relation = $this->getRelation($name);

        $relatedTable = $relation->getRelated()->getTable();
        $selectFields = array_map(function ($field) use ($relatedTable) {
            return $relatedTable . '.' . $field;
        }, $selectFields);

        $relation->addEagerConstraints($models);

        // select $selectFields before counts, since withCount()
        // will add a '*' column by default, which we don't want.
        $relation->select($selectFields);

        if (count($relationCounts)) {
            $relation->withCount($relationCounts);
        }

        $relatedModels = $relation->get();

        $relation->match(
            $relation->initRelation($models, $name),
            $relatedModels,
            $name
        );

        return $relatedModels;
    }
}
