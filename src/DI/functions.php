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

function factory(Closure $factory)
{
    return new FactoryDefinition($factory);
}

function create()
{
    return new CreateDefinition();
}

function classOrCallback($classOrCallback): array
{
    if ($classOrCallback instanceof Closure) {
        return [null, $classOrCallback];
    } elseif (is_callable($classOrCallback)) {
        return [null, Closure::fromCallable($classOrCallback)];
    }

    if (!is_string($classOrCallback)) {
        throw new NotATypeOrCallbackException('Argument is not a class string: ' . gettype($classOrCallback));
    }

    if (!class_exists($classOrCallback)) {
        throw new NotATypeOrCallbackException('Argument is not a known class: ' . $classOrCallback);
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
            if (!class_exists($TypeClass)) {
                throw new NotATypeException("Class {$TypeClass} is not known.");
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
