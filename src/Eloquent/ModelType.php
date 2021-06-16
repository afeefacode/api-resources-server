<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\ModelType as ApiResourcesModelType;

class ModelType extends ApiResourcesModelType
{
    public function created(): void
    {
        parent::created();

        $this->addDefaultRelationResolvers($this->fields);
    }

    protected function getEloquentRelationResolver(string $ModelClass): ModelResolver
    {
        return (new ModelResolver())
            ->modelClass($ModelClass);
    }

    protected function addDefaultRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasResolver()) {
                    $relationName = $entry->getName();
                    $owner = new static::$ModelClass();
                    $relation = $owner->$relationName();
                    $entry->resolve([$this->getEloquentRelationResolver($relation->getRelated()), 'relation']);
                }
            }
        }
    }
}
