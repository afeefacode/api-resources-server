<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\DI\ContainerAwareInterface;

/**
 * @method ActionInput list()
*/
class ActionInput extends ActionResponse implements ToSchemaJsonInterface, ContainerAwareInterface
{
    protected bool $create = false;
    protected bool $update = false;

    public function create($create = true): ActionInput
    {
        $this->create = $create;
        return $this;
    }

    public function update($update = true): ActionInput
    {
        $this->update = $update;
        return $this;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        if ($this->create) {
            $json['create'] = true;
        }

        if ($this->update) {
            $json['update'] = true;
        }

        return $json;
    }

    protected function getNameForException(): string
    {
        return 'input';
    }
}
