<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class SelectValidator extends Validator
{
    protected static string $type = 'Afeefa.SelectValidator';

    public function filled(): SelectValidator
    {
        return $this->param('filled', true);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('filled')
            ->message('{{ fieldLabel }} sollte eine Auswahl enthalten.')
            ->validate(function (?string $value, bool $filled) {
                if ($filled && !$value) {
                    return false;
                }
                return true;
            });
    }
}
