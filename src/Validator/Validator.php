<?php

namespace Afeefa\ApiResources\Validator;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Validator\Rule\RuleBag;
use ArrayObject;

class Validator implements ToSchemaJsonInterface
{
    public static string $type;

    public array $params = [];

    protected RuleBag $rules;

    public function __construct()
    {
        if (!static::$type) {
            throw new MissingTypeException('Missing type for validator of class ' . static::class . '.');
        };

        $this->rules = new RuleBag();
        $this->rules($this->rules);
    }

    public function clone(): Validator
    {
        $validator = new static();

        $arrObject = new ArrayObject($this->params);
        $validator->params = $arrObject->getArrayCopy();
        return $validator;
    }

    protected function param($name, $value): Validator
    {
        $this->params[$name] = $value;
        return $this;
    }

    protected function rules(RuleBag $rules): void
    {
    }

    public function toSchemaJson(): array
    {
        return [
            'type' => static::$type,
            'params' => $this->params,
            'rules' => $this->rules->toSchemaJson(),
        ];
    }
}
