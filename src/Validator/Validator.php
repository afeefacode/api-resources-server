<?php

namespace Afeefa\ApiResources\Validator;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;
use Afeefa\ApiResources\Validator\Rule\RuleBag;
use ArrayObject;
use ReflectionFunction;

class Validator implements ToSchemaJsonInterface
{
    use HasStaticTypeTrait;

    public array $params = [];

    protected RuleBag $rules;

    public function __construct()
    {
        $this->rules = new RuleBag();

        $this->rules->add('filled')
            ->message('{{ fieldLabel }} sollte einen Wert enthalten.')
            ->validate(function ($value, $filled) {
                if ($filled && !$this->valueIsFilled($value)) {
                    return false;
                }
                return true;
            });

        $this->rules($this->rules);
    }

    public function filled(bool $filled = true): static
    {
        return $this->param('filled', $filled);
    }

    public function clone(): Validator
    {
        $validator = new static();
        $arrObject = new ArrayObject($this->params);
        $validator->params = $arrObject->getArrayCopy();
        return $validator;
    }

    public function validate($value): bool
    {
        foreach ($this->rules->getEntries() as $ruleName => $rule) {
            $result = $this->validateRule($ruleName, $value);
            if (!$result) {
                return false;
            }
        }

        return true;
    }

    public function validateRule(string $ruleName, $value): bool
    {
        $rule = $this->rules->get($ruleName);
        $validate = $rule->getValidate();
        $f = new ReflectionFunction($validate);
        $fParams = array_slice($f->getParameters(), 1); // remove '$value' from arg list

        $args = array_map(function ($param) use ($rule) {
            return $this->getParam($param->name, $rule->getDefaultParam());
        }, $fParams);

        $result = $validate($value, ...$args);

        if (!$result) {
            return false;
        }

        return true;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getRules(): RuleBag
    {
        return $this->rules;
    }

    protected function param($name, $value): Validator
    {
        $this->params[$name] = $value;
        return $this;
    }

    protected function getParam(string $name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    protected function rules(RuleBag $rules): void
    {
    }

    protected function valueIsFilled($value): bool
    {
        return !!$value;
    }

    public function toSchemaJson(): array
    {
        return [
            'type' => $this::type(),
            'params' => $this->params,
            'rules' => $this->rules->toSchemaJson(),
        ];
    }
}
