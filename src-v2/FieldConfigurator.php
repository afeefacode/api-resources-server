<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Field\Field;

class FieldConfigurator
{
    private array $perOpValidate = [];

    private array $perOpRequired = [];

    public function onMutation(?callable $validate = null, ?bool $required = null): static
    {
        foreach ([Operation::UPDATE, Operation::CREATE] as $op) {
            if ($validate !== null) {
                $this->perOpValidate[$op->value] = $validate;
            }
            if ($required !== null) {
                $this->perOpRequired[$op->value] = $required;
            }
        }
        return $this;
    }

    public function onUpdate(?callable $validate = null, ?bool $required = null): static
    {
        if ($validate !== null) {
            $this->perOpValidate[Operation::UPDATE->value] = $validate;
        }
        if ($required !== null) {
            $this->perOpRequired[Operation::UPDATE->value] = $required;
        }
        return $this;
    }

    public function onCreate(?callable $validate = null, ?bool $required = null): static
    {
        if ($validate !== null) {
            $this->perOpValidate[Operation::CREATE->value] = $validate;
        }
        if ($required !== null) {
            $this->perOpRequired[Operation::CREATE->value] = $required;
        }
        return $this;
    }

    public function applyToField(Field $field, Operation $op): void
    {
        if (isset($this->perOpRequired[$op->value])) {
            $field->required($this->perOpRequired[$op->value]);
        }
        if (isset($this->perOpValidate[$op->value])) {
            $field->validate($this->perOpValidate[$op->value]);
        }
    }
}
