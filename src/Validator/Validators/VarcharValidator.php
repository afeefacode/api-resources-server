<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class VarcharValidator extends Validator
{
    public static string $type = 'Afeefa.VarcharValidator';

    public function filled(bool $filled = true): VarcharValidator
    {
        return $this->param('filled', $filled);
    }

    public function null(bool $null = true): VarcharValidator
    {
        return $this->param('null', $null);
    }

    public function min(int $min): VarcharValidator
    {
        return $this->param('min', $min);
    }

    public function max(int $max): VarcharValidator
    {
        return $this->param('max', $max);
    }

    public function regex($regex)
    {
        return $this->param('regex', $regex);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('string')
            ->default(true)
            ->message('{{ fieldLabel }} sollte eine Zeichenkette sein.')
            ->validate(function ($value) {
                if (is_null($value)) { // validate null in null-rule
                    return true;
                }
                if (!is_string($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('null')
            ->message('{{ fieldLabel }} sollte eine Zeichenkette sein.')
            ->validate(function ($value, $null) {
                if (!$null && is_null($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('filled')
            ->message('{{ fieldLabel }} sollte einen Wert enthalten.')
            ->validate(function ($value, $filled) {
                if ($filled && !$value) {
                    return false;
                }
                return true;
            });

        $rules->add('min')
            ->message('{{ fieldLabel }} sollte mindestens {{ param }} Zeichen beinhalten.')
            ->validate(function ($value, $filled, $min) {
                if ($min === null) {
                    return true;
                }
                if (!$filled && !$value) {
                    return true;
                }
                if (strlen($value) < $min) {
                    return false;
                }
                return true;
            });

        $rules->add('max')
            ->message('{{ fieldLabel }} sollte maximal {{ param }} Zeichen beinhalten.')
            ->validate(function ($value, $max) {
                if ($max === null) {
                    return true;
                }
                if (strlen($value) > $max) {
                    return false;
                }
                return true;
            });

        $rules->add('regex')
            ->message('{{ fieldLabel }} sollte ein gültiger Wert sein.')
            ->validate(function ($value, $regex) {
                if ($regex === null) {
                    return true;
                }
                if (!preg_match($regex, $value)) {
                    return false;
                }
                return true;
            });
    }
}
