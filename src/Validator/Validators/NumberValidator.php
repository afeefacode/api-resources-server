<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class NumberValidator extends Validator
{
    public static string $type = 'Afeefa.NumberValidator';

    public function filled(bool $filled = true): NumberValidator
    {
        return $this->param('filled', $filled);
    }

    public function null(bool $null = true): NumberValidator
    {
        return $this->param('null', $null);
    }

    public function max(float $max): NumberValidator
    {
        return $this->param('max', $max);
    }

    public function min(float $min): NumberValidator
    {
        return $this->param('min', $min);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('number')
            ->default(true)
            ->message('{{ fieldLabel }} sollte eine Zahl sein.')
            ->validate(function ($value) {
                if (is_null($value)) { // validate null in null-rule
                    return true;
                }
                // a numeric string
                if (is_string($value)) {
                    return false;
                }
                // not numeric, e.g. bool
                if (!is_numeric($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('null')
            ->default(true)
            ->message('{{ fieldLabel }} sollte eine Zahl sein.')
            ->validate(function ($value, $null) {
                // null only allowed if set
                if (!$null && is_null($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('filled')
            ->message('{{ fieldLabel }} sollte einen Wert enthalten.')
            ->validate(function ($value, $filled) {
                // must not be empty (but 0 is okay)
                if ($filled && !$value && $value !== 0) {
                    return false;
                }
                return true;
            });

        $rules->add('max')
            ->message('{{ fieldLabel }} sollte maximal {{ param }} sein.')
            ->validate(function ($value, $max) {
                if ($max === null) {
                    return true;
                }
                if ($value > $max) {
                    return false;
                }
                return true;
            });

        $rules->add('min')
            ->default(0)
            ->message('{{ fieldLabel }} sollte mindestens {{ param }} sein.')
            ->validate(function ($value, $min, $filled) {
                if ($min === null) {
                    return true;
                }
                if (is_null($value)) {
                    return true;
                }
                if ($value < $min) {
                    return false;
                }
                return true;
            });
    }
}
