<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\Type;

class ModelType extends Type
{
    public static string $ModelClass;

    public function created(): void
    {
        parent::created();

        $this->addDefaultRelationResolvers($this->fields);
        $this->addDefaultSaveRelationResolvers($this->updateFields);
        $this->addDefaultSaveRelationResolvers($this->createFields);
    }

    protected function addDefaultRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasResolver()) {
                    $entry->resolve([ModelRelationResolver::class, 'get_relation']);
                }
            }
        }
    }

    protected function addDefaultSaveRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasSaveResolver()) {
                    $entry->resolveSave([ModelRelationResolver::class, 'save_relation']);
                }
            }
        }
    }
}
