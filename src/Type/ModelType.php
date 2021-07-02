<?php

namespace Afeefa\ApiResources\Type;

use Afeefa\ApiResources\Field\Field;
use Afeefa\ApiResources\Field\FieldBag;

class ModelType extends Type
{
    public static string $type = 'Afeefa.ModelType';

    public static string $ModelClass;

    protected FieldBag $updateFields;

    protected FieldBag $createFields;

    public function created(): void
    {
        parent::created();

        $this->updateFields = $this->container->create(function (FieldBag $fieldBag) {
            $fieldBag->original($this->fields);
        });
        $this->updateFields($this->updateFields);

        $this->createFields = $this->container->create(function (FieldBag $fieldBag) {
            $fieldBag->original($this->updateFields);
        });
        $this->createFields($this->createFields);
    }

    public function hasUpdateField(string $name): bool
    {
        return $this->updateFields->has($name);
    }

    public function getUpdateField(string $name): Field
    {
        return $this->updateFields->get($name);
    }

    public function hasCreateField(string $name): bool
    {
        return $this->createFields->has($name);
    }

    public function getCreateField(string $name): Field
    {
        return $this->createFields->get($name);
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        $json = $this->insertAfter('fields', $json, 'update_fields', $this->updateFields->toSchemaJson());
        $json = $this->insertAfter('update_fields', $json, 'create_fields', $this->createFields->toSchemaJson());

        return $json;
    }

    protected function updateFields(FieldBag $fields): void
    {
    }

    protected function createFields(FieldBag $fields): void
    {
    }

    private function insertAfter($afterKey, array $array, $newKey, $newValue)
    {
        $new = [];
        foreach ($array as $k => $value) {
            $new[$k] = $value;
            if ($k === $afterKey) {
                $new[$newKey] = $newValue;
            }
        }
        return $new;
    }
}
