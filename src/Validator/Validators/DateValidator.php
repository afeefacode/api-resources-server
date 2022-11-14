<?php

namespace Afeefa\ApiResources\Validator\Validators;

use Afeefa\ApiResources\Validator\Rule\RuleBag;
use Afeefa\ApiResources\Validator\Validator;
use DateTime;
use Exception;

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
                // null may be okay, validate null in null-rule
                if (is_null($value)) {
                    return true;
                }

                // value is date
                if ($value instanceof DateTime) {
                    return true;
                }

                // value is iso date string
                if (is_string($value)) {
                    // unsupported value
                    try {
                        new DateTime($value);
                    } catch (Exception $e) {
                        return false;
                    }

                    // value is an iso date
                    // YYYY-MM-DDTHH:mm:ss.sssZ
                    // https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toISOString
                    // https://www.php.net/manual/de/class.datetimeinterface.php#datetimeinterface.constants.rfc3339-extended
                    $dateTime = DateTime::createFromFormat(DateTime::RFC3339_EXTENDED, $value);
                    if ($dateTime) {
                        return true;
                    }
                }

                return false;
            });

        $rules->add('null')
            ->default(true)
            ->message('{{ fieldLabel }} sollte ein Datum sein.')
            ->validate(function ($value, $null) {
                // null only allowed if set
                if (!$null && is_null($value)) {
                    return false;
                }

                return true;
            });
    }
}
