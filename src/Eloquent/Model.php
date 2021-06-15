<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Model\ModelInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

class Model extends EloquentModel implements ModelInterface
{
    public static $type = 'Model';

    protected $visibleFields = [];

    public function apiResourcesGetType(): string
    {
        return static::$type;
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
        foreach ($this->getAttributes() as $name => $value) {
            if (in_array($name, $this->visibleFields)) {
                if ($name === 'type') {
                    continue;
                }

                $json[$name] = $value;

                if ($name === 'id') {
                    $json['type'] = static::$type;
                }
            }
        }
        return $json;
    }
}
