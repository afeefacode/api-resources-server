<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class IntValidator extends Validator
{
    public static string $type = 'Afeefa.IntValidator';

    public function filled(bool $filled = true): IntValidator
    {
        return $this->param('filled', $filled);
    }

    public function null(bool $null = true): IntValidator
    {
        return $this->param('null', $null);
    }

    public function max(int $max): IntValidator
    {
        return $this->param('max', $max);
    }

    public function min(int $min): IntValidator
    {
        return $this->param('min', $min);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('int')
            ->default(true)
            ->message('{{ fieldLabel }} sollte eine Zahl sein.')
            ->validate(function ($value) {
                // null may be okay, validate null in null-rule
                if (is_null($value)) {
                    return true;
                }
                // only int numbers allowed
                if (!is_int($value)) {
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
            ->default(1)
            ->message('{{ fieldLabel }} sollte mindestens {{ param }} sein.')
            ->validate(function ($value, $min) {
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
