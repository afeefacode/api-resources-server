<?php

namespace Afeefa\ApiResources\Field\Fields;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class SetAttributeCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        if ($value) {
            return explode(',', $value);
        }
        return [];
    }

    public function set($model, $key, $value, $attributes)
    {
        if ($value && count($value)) { // if a non emtpy array, store a string
            return implode(',', $value);
        }
        return null;
    }
}
