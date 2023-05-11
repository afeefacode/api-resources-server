<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Utils\HasStaticTypeTrait;

class Attribute extends Field
{
    use HasStaticTypeTrait;

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        if ($this->isMutation && $this->hasDefaultValue()) {
            $json['default'] = $this->default;
        }

        return $json;
    }
}
