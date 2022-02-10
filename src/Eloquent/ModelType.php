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
        $this->addDefaultMutationRelationResolvers($this->updateFields);
        $this->addDefaultMutationRelationResolvers($this->createFields);
    }

    protected function addDefaultRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasResolver()) {
                    $entry->resolve(
                        [ModelRelationResolver::class, 'get_relation'],
                        ['is_eloquent_relation' => true]
                    );
                }
            }
        }
    }

    protected function addDefaultMutationRelationResolvers(FieldBag $fields)
    {
        foreach (array_values($fields->getEntries()) as $entry) {
            if ($entry instanceof Relation) {
                if (!$entry->hasResolver()) {
                    $entry->resolve([ModelRelationResolver::class, 'save_relation']);
                }
            }
        }
    }
}
