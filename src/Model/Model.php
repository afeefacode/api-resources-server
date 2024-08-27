<?php

namespace Afeefa\ApiResources\Model;

use JsonSerializable;

#[\AllowDynamicProperties]
class Model implements ModelInterface, JsonSerializable
{
    protected array $visibleFields = [];

    public string $id;

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

    public static function fromSingle(string $type, $object = []): static
    {
        if ($object instanceof ModelInterface) {
            return $object;
        }

        $model = new static();
        $model->type = $type;
        $model->setAttributes($object);
        return $model;
    }

    public function __construct(array $attributes = [])
    {
        $this->setAttributes($attributes);
    }

    public function apiResourcesGetId(): ?string
    {
        return $this->id ?? null;
    }

    public function apiResourcesGetType(): string
    {
        return $this->type;
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

    public function jsonSerialize(): mixed
    {
        $json = [];

        if ($this->id ?? null) {
            $json['id'] = $this->id;
        }

        if ($this->type ?? null) {
            $json['type'] = $this->type;
        }

        foreach ($this as $name => $value) {
            if (in_array($name, $this->visibleFields)) {
                if ($name === 'id' || $name === 'type') {
                    continue;
                }

                if ($value instanceof JsonSerializable) {
                    $value = $value->jsonSerialize();
                }

                if (is_array($value)) {
                    foreach ($value as $index => $element) {
                        if ($element instanceof JsonSerializable) {
                            $value[$index] = $element->jsonSerialize();
                        }
                    }
                }

                $json[$name] = $value;
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
