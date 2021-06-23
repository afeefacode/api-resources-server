<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class VarcharValidator extends Validator
{
    public static string $type = 'Afeefa.VarcharValidator';

    public function filled(): VarcharValidator
    {
        return $this->param('filled', true);
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
        $rules->add('filled')
            ->message('{{ fieldLabel }} sollte einen Wert enthalten.')
            ->validate(function (?string $value, bool $filled) {
                if ($filled && !$value) {
                    return false;
                }
                return true;
            });

        $rules->add('min')
            ->message('{{ fieldLabel }} sollte mindestens {{ param }} Zeichen beinhalten.')
            ->validate(function (?string $value, bool $filled, ?int $min) {
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
            ->validate(function (?string $value, ?int $max) {
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
            ->validate(function (?string $value, ?string $regex) {
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
