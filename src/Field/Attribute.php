<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Utils\HasStaticTypeTrait;

/**
 * @method Attribute owner($owner)
 * @method Attribute name(string $name)
 * @method Attribute validate(Closure $callback)
 * @method Attribute validator(Validator $validator)
 * @method Attribute required(bool $required = true)
 * @method Attribute resolve(string|callable|Closure $classOrCallback)
 * @method Attribute resolveParam(string $key, $value)
 * @method Attribute resolveParams(array $params)
*/
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
