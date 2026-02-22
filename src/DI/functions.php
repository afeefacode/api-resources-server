<?php

namespace Afeefa\ApiResources\DI;

use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackArgumentException;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeHintException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use Afeefa\ApiResources\Exception\Exceptions\TooManyCallbackArgumentsException;
use Closure;
use ReflectionFunction;
use ReflectionNamedType;

function classOrCallback($classOrCallback): array
{
    if ($classOrCallback instanceof Closure) {
        return [null, $classOrCallback];
    } elseif (is_callable($classOrCallback)) {
        return [null, Closure::fromCallable($classOrCallback)];
    }

    if (!is_string($classOrCallback)) {
        throw new NotATypeOrCallbackException('Argument is not a class or interface string: ' . gettype($classOrCallback));
    }

    if (!class_exists($classOrCallback) && !interface_exists($classOrCallback)) {
        throw new NotATypeOrCallbackException('Argument is not a known class or interface: ' . $classOrCallback);
    }

    return [$classOrCallback, null];
}

function getCallbackArgumentTypes(Closure $callback, $min = 0, $max = 10): array
{
    $TypeClasses = [];

    $f = new ReflectionFunction($callback);
    $params = $f->getParameters();

    foreach ($params as $param) {
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType) {
            $TypeClass = $type->getName();
            if (!class_exists($TypeClass) && !interface_exists($TypeClass)) {
                throw new NotATypeException("Class or interface {$TypeClass} is not known.");
            }
            $TypeClasses[] = $TypeClass;
            continue;
        }
        throw new MissingTypeHintException("Callback variable \${$param->getName()} does provide a type hint.");
    }

    if (count($TypeClasses) < $min) {
        $min = $min - 1;
        $s = $max > 1 ? 's' : '';
        throw new MissingCallbackArgumentException("Callback must provide more than {$min} argument{$s}.");
    } elseif (count($TypeClasses) > $max) {
        $s = $max > 1 ? 's' : '';
        throw new TooManyCallbackArgumentsException("Callback may only provide {$max} argument{$s}.");
    }

    return $TypeClasses;
}

function getCallbackArgumentType(Closure $callback): string
{
    $TypeClasses = getCallbackArgumentTypes($callback, 1, 1);
    return $TypeClasses[0];
}

/**
 * Invokes a resolver callback (action, attribute, relation, or mutation relation resolver)
 * with DI support.
 *
 * The callback's first argument defines the resolver type and is created as a fresh
 * instance via $container->create(). Additional arguments are injected as singletons
 * via $container->get(), allowing access to services like ContainerInterface or BackendApi.
 *
 * An optional $beforeInvoke callback receives the resolver instance before the main
 * callback is invoked — useful when the resolver needs configuration first (e.g.
 * MutationRelationResolver needs relation/fieldsToSave set before the callback runs).
 *
 * Returns the resolver instance (first argument), or null if the callback has no arguments.
 */
function invokeResolverCallback(Closure $callback, Container $container, ?Closure $beforeInvoke = null): ?object
{
    $TypeClasses = getCallbackArgumentTypes($callback);

    if (count($TypeClasses) === 0) {
        return null;
    }

    $arguments = [];
    foreach ($TypeClasses as $i => $TypeClass) {
        $arguments[] = $i === 0
            ? $container->create($TypeClass)
            : $container->get($TypeClass);
    }

    if ($beforeInvoke) {
        $beforeInvoke($arguments[0]);
    }

    $callback(...$arguments);

    return $arguments[0];
}
