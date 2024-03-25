<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;

class LinkManyValidator extends Validator
{
    protected static string $type = 'Afeefa.LinkManyValidator';

    public function max(int $max): LinkManyValidator
    {
        return $this->param('max', $max);
    }

    protected function rules(RuleBag $rules): void
    {
        $rules->add('max')
            ->message('{{ fieldLabel }} sollte maximal {{ param }} Elemente beinhalten.')
            ->validate(function ($value, $max) {
                if ($max === null) {
                    return true;
                }
                // empty value cannot exceed max
                if (count($value) && count($value) > $max) {
                    return false;
                }
                return true;
            });
    }
}
