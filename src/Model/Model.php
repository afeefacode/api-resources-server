<?php

namespace Afeefa\ApiResources\Model;

use JsonSerializable;

class Model implements ModelInterface, JsonSerializable
{
    protected array $visibleFields = [];

    public string $type;

    /**
     * @return ModelInterface[]
     */
    public static function fromList(string $type, array $objects): array
    {
        $models = [];
        foreach ($objects as $object) {
            $models[] = static::fromSingle($type, $object);
        }
        return $models;
    }

    public static function fromSingle(string $type, $object): ModelInterface
    {
        if ($object instanceof ModelInterface) {
            return $object;
        }

        $model = new Model();
        $model->type = $type;
        $model->setAttributes($object);
        return $model;
    }

    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    public function apiResourcesGetType(): string
    {
        return $this->type;
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
        foreach ($this as $name => $value) {
            if (in_array($name, $this->visibleFields)) {
                if ($name === 'type') {
                    continue;
                }

                $json[$name] = $value;

                if ($name === 'id') {
                    $json['type'] = $this->type;
                }
            }
        }
        return $json;
    }

    protected function setAttributes(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }
}
