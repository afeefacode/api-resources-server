<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\DateValidator;
use DateTime;
use PHPUnit\Framework\TestCase;

class DateValidatorTest extends TestCase
{
    public function test_default_date()
    {
        $validator = $this->createDateValidator();

        foreach ([
            new DateTime(),
            '2022-11-09T16:43:58.355Z',
            '2016-07-16T01:22:04.324+1030',
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('date', $value));
        }

        foreach ([
            '2016-07-16',
            '2016-07-16T01:22:04+1030',
            '2016-07-16T1:22:04.324+1030',
            'test',
            '1',
            '1.1',
            '-1',
            -1,
            3.0,
            1.1,
            -1.1,
            [],
            $this,
            '',
            fn () => null
        ] as $value) {
            $this->assertFalse($validator->validateRule('date', $value));
        }
    }

    public function test_filled()
    {
        $validator = $this->createDateValidator();

        foreach ([
            new DateTime(),
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('filled', $value));
        }

        $validator = $this->createDateValidator()
            ->filled();

        foreach ([
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('filled', $value));
        }
    }

    protected function createDateValidator(): DateValidator
    {
        return (new Container())
            ->create(DateValidator::class);
    }
}
