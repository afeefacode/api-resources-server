<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Utils\HasStaticTypeTrait;

class Attribute extends Field
{
    use HasStaticTypeTrait;

    protected array $dependingAttributes = [];

    public function select($attributeOrAttributes): Attribute
    {
        $this->dependingAttributes = is_array($attributeOrAttributes) ? $attributeOrAttributes : [$attributeOrAttributes];
        return $this;
    }

    public function getDependingAttributes(): array
    {
        return $this->dependingAttributes;
    }

    public function hasDependingAttributes(): bool
    {
        return count($this->dependingAttributes) > 0;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        if ($this->isMutation && $this->hasDefaultValue()) {
            $json['default'] = $this->default;
        }

        return $json;
    }
}
