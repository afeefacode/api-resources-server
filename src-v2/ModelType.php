<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Eloquent\ModelType as EloquentModelType;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;

class ModelType extends EloquentModelType
{
    use DefinesFields;

    public function created(): void
    {
        $this->setupV2Fields();

        if (!isset(static::$ModelClass) || !class_exists(static::$ModelClass)) {
            throw new InvalidConfigurationException(
                'Missing Eloquent model in class ' . static::class . '.'
            );
        }

        static::$ModelClass::registerMorphType();

        $this->addDefaultRelationResolvers($this->fields);
        $this->addDefaultMutationRelationResolvers($this->updateFields);
        $this->addDefaultMutationRelationResolvers($this->createFields);
    }
}
