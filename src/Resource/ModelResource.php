<?php

namespace Afeefa\ApiResources\Resource;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;

class ModelResource extends Resource
{
    protected string $ModelTypeClass;

    protected function actions(ActionBag $actions): void
    {
        $actions->add('list', function (Action $action) {
            $action->response($this->ModelTypeClass);
        });
    }
}
