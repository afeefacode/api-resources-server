<?php

namespace Afeefa\ApiResources\Validator\Sanitizer;

use Afeefa\ApiResources\Bag\BagEntry;
use Closure;

class Sanitizer extends BagEntry
{
    protected Closure $sanitize;

    protected $default = null;

    public function sanitize(Closure $sanitize): Sanitizer
    {
        $this->sanitize = $sanitize;
        return $this;
    }

    public function getSanitize(): Closure
    {
        return $this->sanitize;
    }

    public function sanitizeValue($value)
    {
        return ($this->sanitize)($value);
    }

    public function default($default): Sanitizer
    {
        $this->default = $default;
        return $this;
    }

    public function hasDefaultParam(): bool
    {
        return isset($this->default);
    }

    public function getDefaultParam()
    {
        return $this->default;
    }

    public function toSchemaJson(): array
    {
        if (!is_null($this->default)) {
            return ['default' => $this->default];
        }

        return [];
    }
}
