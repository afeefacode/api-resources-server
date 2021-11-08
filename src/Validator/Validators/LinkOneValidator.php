<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class LinkOneValidator extends Validator
{
    protected static string $type = 'Afeefa.LinkOneValidator';

    public function filled(): LinkOneValidator
    {
        return $this->param('filled', true);
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
    }
}
