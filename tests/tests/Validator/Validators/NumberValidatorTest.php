<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\NumberValidator;
use PHPUnit\Framework\TestCase;

class NumberValidatorTest extends TestCase
{
    public function test_default_number()
    {
        $validator = $this->createNumberValidator();

        foreach ([
            1,
            1.1,
            0,
            -1,
            -1.1,
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('number', $value));
        }

        foreach ([
            'test',
            '1',
            '1.1',
            [],
            $this
        ] as $value) {
            $this->assertFalse($validator->validateRule('number', $value));
        }
    }

    public function test_default_min()
    {
        $validator = $this->createNumberValidator();

        foreach ([
            0,
            1,
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            -.00001,
            -1,
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_filled()
    {
        $validator = $this->createNumberValidator()
            ->filled();

        foreach ([
            0,
            1,
            1.1
        ] as $value) {
            $this->assertTrue($validator->validateRule('filled', $value));
        }

        foreach ([
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('filled', $value));
        }
    }

    public function test_null()
    {
        $validator = $this->createNumberValidator();

        foreach ([
            1,
            1.2,
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('null', $value));
        }

        $validator = $this->createNumberValidator()
            ->null(false);

        foreach ([
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('null', $value));
        }
    }

    public function test_max()
    {
        $validator = $this->createNumberValidator()
            ->max(5);

        foreach ([
            -4,
            0,
            5
        ] as $value) {
            $this->assertTrue($validator->validateRule('max', $value));
        }

        foreach ([
            5.00001,
            6,
            100
        ] as $value) {
            $this->assertFalse($validator->validateRule('max', $value));
        }
    }

    public function test_min()
    {
        $validator = $this->createNumberValidator()
            ->min(5);

        foreach ([
            5.0000,
            5,
            6
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            4,
            4.9999,
            0,
            -1
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }

        $validator = $this->createNumberValidator()
            ->filled()
            ->min(5.1);

        foreach ([
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        $validator = $this->createNumberValidator()
            ->min(0);

        foreach ([
            0
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            -1
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }
    }

    protected function createNumberValidator(): NumberValidator
    {
        return (new Container())
            ->create(NumberValidator::class);
    }
}
