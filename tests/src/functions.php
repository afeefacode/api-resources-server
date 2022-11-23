<?php

namespace Afeefa\ApiResources\Test;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Type\Type;
use Closure;
use Faker\Generator;
use Illuminate\Support\Collection;

function T(string $type, bool $create = true): ?string
{
    // find container entry of Type::class with type() === type
    $container = ApiResourcesTest::$staticContainer;
    $entries = $container->entries();
    foreach (array_keys($entries) as $Class) {
        if (is_subclass_of($Class, Type::class)) {
            if ($Class::type() === $type) {
                return $Class;
            }
        }
    }

    if ($create) {
        // no entry found, create one
        $type = (new TypeBuilder($container))->type($type)->get();
        return $type::class;
    }

    return null;
}

function createApiWithSingleType(
    string $typeName = 'Test.Type',
    ?Closure $fieldsCallback = null,
    ?Closure $updateFieldsCallback = null,
    ?Closure $createFieldsCallback = null,
    ?Closure $addActionCallback = null
): Api {
    $container = ApiResourcesTest::$staticContainer;
    (new TypeBuilder($container))->type($typeName, $fieldsCallback, $updateFieldsCallback, $createFieldsCallback)->get();

    if (!$addActionCallback) {
        $addActionCallback = function (Closure $addAction) use ($typeName) {
            $addAction('test_action', T($typeName), function (Action $action) use ($typeName) {
                $action->resolve(function () {
                });
            });
        };
    }

    return createApiWithSingleResource($addActionCallback);
}

function createApiWithSingleResource(?Closure $addActionCallback = null): Api
{
    $container = ApiResourcesTest::$staticContainer;
    return (new ApiBuilder($container))
        ->api('Test.Api', function (Closure $addResource) use ($addActionCallback) {
            $addResource('Test.Resource', $addActionCallback);
        })
        ->get();
}

function fake(): Generator
{
    $container = ApiResourcesTest::$staticContainer;
    $faker = $container->get(Generator::class);
    return $faker;
}

function toArray(mixed $value, bool $onlyVisible = true): mixed
{
    if ($value instanceof Collection) {
        $value = $value->all();
    }

    if (is_object($value) && method_exists($value, 'toArray')) {
        return $value->toArray($onlyVisible);
    } elseif (is_array($value)) {
        return array_map(function ($element) use ($onlyVisible) {
            return toArray($element, $onlyVisible);
        }, $value);
    }
    return $value;
}
