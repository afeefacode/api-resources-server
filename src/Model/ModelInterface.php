<?php

namespace Afeefa\ApiResources\Model;

interface ModelInterface
{
    public function apiResourcesGetType(): string;

    public function apiResourcesSetAttribute(string $name, $value): void;

    public function apiResourcesSetRelation(string $name, $value): void;

    public function apiResourcesSetVisibleFields(array $fields): void;
}
