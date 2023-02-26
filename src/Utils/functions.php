<?php

namespace Afeefa\ApiResources\Utils;

function toArray(mixed $value, bool $onlyVisible = true): mixed
{
    if (is_object($value) && method_exists($value, 'toArray')) {
        return $value->toArray($onlyVisible);
    } elseif (is_array($value)) {
        return array_map(function ($element) use ($onlyVisible) {
            return toArray($element, $onlyVisible);
        }, $value);
    }
    return $value;
}
