<?php

namespace Afeefa\ApiResources\Action;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\DI\ContainerAwareInterface;

/**
 * @method ActionInput list()
*/
class ActionInput extends ActionResponse implements ToSchemaJsonInterface, ContainerAwareInterface
{
    protected function getNameForException(): string
    {
        return 'input';
    }
}
