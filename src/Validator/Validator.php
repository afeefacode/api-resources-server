<?php

namespace Afeefa\ApiResources\Validator;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;
use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Sanitizer\SanitizerBag;
use ArrayObject;
use ReflectionFunction;

class Validator implements ToSchemaJsonInterface
{
    use HasStaticTypeTrait;

    public array $params = [];

    protected RuleBag $rules;
    protected SanitizerBag $sanitizers;

    public function __construct()
    {
        $this->sanitizers = new SanitizerBag();
        $this->sanitizers($this->sanitizers);

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

    public function sanitize($value)
    {
        foreach ($this->sanitizers->getEntries() as $sanitizerName => $sanitizer) {
            $value = $this->sanitizeRule($sanitizerName, $value);
        }
        return $value;
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

    public function sanitizeRule(string $sanitizerName, $value)
    {
        $sanitizer = $this->sanitizers->get($sanitizerName);
        $sanitize = $sanitizer->getSanitize();
        $f = new ReflectionFunction($sanitize);
        $fParams = array_slice($f->getParameters(), 1); // remove '$value' from arg list

        $args = array_map(function ($param) use ($sanitizer) {
            return $this->getParam($param->name, $sanitizer->getDefaultParam());
        }, $fParams);

        return $sanitize($value, ...$args);
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
        if (is_null($value)) {
            unset($this->params[$name]);
        } else {
            if ($this->sanitizers->has($name) && $this->sanitizers->get($name)->getDefaultParam() === $value) {
                unset($this->params[$name]);
            } elseif ($this->rules->has($name) && $this->rules->get($name)->getDefaultParam() === $value) {
                unset($this->params[$name]);
            } else {
                $this->params[$name] = $value;
            }
        }
        return $this;
    }

    protected function getParam(string $name, $default = null)
    {
        return $this->params[$name] ?? $default;
    }

    protected function sanitizers(SanitizerBag $sanitizers): void
    {
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
        $json = [
            'type' => $this::type(),
            'params' => $this->params
        ];

        $sanizisersJson = $this->sanitizers->toSchemaJson();
        if (!empty($sanizisersJson)) {
            $json['sanitizers'] = $sanizisersJson;
        }

        $json['rules'] = $this->rules->toSchemaJson();

        return $json;
    }
}
