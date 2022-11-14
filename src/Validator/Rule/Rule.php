<?php

namespace Afeefa\ApiResources\Validator\Rule;

use Afeefa\ApiResources\Bag\BagEntry;
use Closure;

class Rule extends BagEntry
{
    protected string $message = '{{ fieldLabel }} ist ungültig.';

    protected Closure $validate;

    protected $default = null;

    public function message($message)
    {
        $this->message = $message;
        return $this;
    }

    public function validate($validate): Rule
    {
        $this->validate = $validate;
        return $this;
    }

    public function getValidate(): Closure
    {
        return $this->validate;
    }

    public function default($default): Rule
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

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toSchemaJson(): array
    {
        $json = [
            'message' => $this->message
        ];

        if (!is_null($this->default)) {
            $json['default'] = $this->default;
        }

        return $json;
    }
}
