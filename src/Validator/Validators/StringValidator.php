<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class StringValidator extends Validator
{
    public static string $type = 'Afeefa.StringValidator';

    public function null(bool $null = true): StringValidator
    {
        return $this->param('null', $null);
    }

    public function min(int $min): StringValidator
    {
        return $this->param('min', $min);
    }

    public function max(int $max): StringValidator
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
            ->default(true)
            ->message('{{ fieldLabel }} sollte eine Zeichenkette sein.')
            ->validate(function ($value, $null) {
                if (!$null && is_null($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('min')
            ->message('{{ fieldLabel }} sollte mindestens {{ param }} Zeichen beinhalten.')
            ->validate(function ($value, $min) {
                if ($min === null) {
                    return true;
                }
                if (is_null($value) || $value === '') { // validate in null/filled
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
                if (is_null($value)) { // validate in null
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
