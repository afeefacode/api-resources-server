<?php

use function Afeefa\ApiResources\Test\toArray as testToArray;

function toArray(mixed $value, bool $onlyVisible = true): mixed
{
    return testToArray($value, $onlyVisible);
}
