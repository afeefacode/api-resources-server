<?php

namespace Afeefa\ApiResources\Validator\Rule;

use Afeefa\ApiResources\Bag\BagEntry;

class Rule extends BagEntry
{
    protected string $message = '{{ fieldLabel }} ist ungültig.';

    protected $validate;

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

    public function toSchemaJson(): array
    {
        $json = [
            'message' => $this->message
        ];

        return $json;
    }
}
