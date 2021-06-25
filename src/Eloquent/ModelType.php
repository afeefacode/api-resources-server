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

    protected function getEloquentRelationResolver(ModelType $type): ModelResolver
    {
        return (new ModelResolver())->type($type);
    }

    protected function addDefaultRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasResolver()) {
                    $type = $entry->getRelatedTypeInstance();
                    $entry->resolve([$this->getEloquentRelationResolver($type), 'relation']);
                }
            }
        }
    }
}
