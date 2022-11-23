<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\Type;

class ModelType extends Type
{
    public static string $ModelClass;

    public function created(): void
    {
        parent::created();

        if (!isset(static::$ModelClass) || !class_exists(static::$ModelClass)) {
            throw new InvalidConfigurationException('Missing Eloquent model in class ' . static::class . '.');
        }

        static::$ModelClass::registerMorphType();

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
                    $isList = $entry->getRelatedType()->isList();
                    $isLink = $entry->getRelatedType()->isLink();

                    if ($isLink) {
                        if ($isList) {
                            $entry->resolve([ModelRelationResolver::class, 'save_link_many_relation']);
                        } else {
                            $entry->resolve([ModelRelationResolver::class, 'save_link_one_relation']);
                        }
                    } else {
                        if ($isList) {
                            $entry->resolve([ModelRelationResolver::class, 'save_has_many_relation']);
                        } else {
                            $entry->resolve([ModelRelationResolver::class, 'save_has_one_relation']);
                        }
                    }
                }
            }
        }
    }
}
