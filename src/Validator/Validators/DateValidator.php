<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class DateValidator extends Validator
{
    public static string $type = 'Afeefa.DateValidator';

    public function filled(bool $filled = true): DateValidator
    {
        return $this->param('filled', $filled);
    }

    public function null(bool $null = true): DateValidator
    {
        return $this->param('null', $null);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('date')
            ->default(true)
            ->message('{{ fieldLabel }} sollte ein Datum sein.')
            ->validate(function ($value) {
                if (is_null($value)) { // validate null in null-rule
                    return true;
                }
                if (!strtotime($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('null')
            ->message('{{ fieldLabel }} sollte ein Datum sein.')
            ->validate(function ($value, $null) {
                if (!$null && is_null($value)) {
                    return false;
                }
                return true;
            });

        $rules->add('filled')
            ->message('{{ fieldLabel }} sollte ein Datum sein.')
            ->validate(function ($value, $filled) {
                if ($filled && !$value) {
                    return false;
                }
                return true;
            });
    }
}
