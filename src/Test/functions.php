<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Resource\ResourceBag;
use Closure;

function T(string $type): string
{
    return TypeRegistry::getOrCreate($type)::class;
}

function createApiWithSingleType(
    string $typeName,
    ?Closure $fieldsCallback = null,
    ?Closure $actionsCallback = null
): Api {
    (new TypeBuilder())->type($typeName, $fieldsCallback);

    if (!$actionsCallback) {
        $actionsCallback = function (ActionBag $actions) use ($typeName) {
            $actions->add('test_action', function (Action $action) use ($typeName) {
                $action->response(T($typeName));
                $action->resolve(function () {
                });
            });
        };
    }

    return createApiWithSingleResource($actionsCallback);
}

function createApiWithSingleResource(?Closure $actionsCallback = null): Api
{
    $resource = (new ResourceBuilder())
        ->resource('Test.Resource', $actionsCallback)
        ->get();

    return (new ApiBuilder())
        ->api(
            'Test.Api',
            function (ResourceBag $resources) use ($resource) {
                $resources->add($resource::class);
            }
        )
        ->useTestContainer()
        ->get();
}
