<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Model\ModelInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class Model extends EloquentModel implements ModelInterface
{
    use HasEagerLimit;

    public static $type = 'Model';

    protected $visibleFields = [];

    protected $keyType = 'string';

    public function getTypeAttribute(): string
    {
        return static::$type;
    }

    public function apiResourcesGetId(): ?string
    {
        return $this->id ?? null;
    }

    public function apiResourcesGetType(): string
    {
        return static::$type;
    }

    public function apiResourcesSetAttribute(string $name, $value): void
    {
        $this->$name = $value;
    }

    public function apiResourcesSetRelation(string $name, $value): void
    {
        $this->$name = $value;
    }

    public function apiResourcesSetVisibleFields(array $fields): void
    {
        $this->visibleFields = $fields;
    }

    public function jsonSerialize()
    {
        $json = [];

        foreach ($this->visibleFields as $visibleFieldName) {
            $json[$visibleFieldName] = $this->$visibleFieldName;
        }

        return $json;
    }
}
