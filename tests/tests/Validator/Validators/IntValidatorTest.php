<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\IntValidator;
use PHPUnit\Framework\TestCase;

class IntValidatorTest extends TestCase
{
    public function test_default_int()
    {
        $validator = $this->createIntValidator();

        foreach ([
            0,
            1,
            1000,
            -1,
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('int', $value));
        }

        foreach ([
            'test',
            '1',
            '1.1',
            '-1',
            3.0,
            1.1,
            -1.1,
            [],
            $this,
            '',
            fn () => null
        ] as $value) {
            $this->assertFalse($validator->validateRule('int', $value));
        }
    }

    public function test_default_min()
    {
        $validator = $this->createIntValidator();

        foreach ([
            1,
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            0,
            -1,
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_filled()
    {
        $validator = $this->createIntValidator()
            ->filled();

        foreach ([
            0,
            1
        ] as $value) {
            $this->assertTrue($validator->validateRule('filled', $value));
        }

        foreach ([
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('filled', $value));
        }
    }

    public function test_max()
    {
        $validator = $this->createIntValidator()
            ->max(5);

        foreach ([
            4,
            5
        ] as $value) {
            $this->assertTrue($validator->validateRule('max', $value));
        }

        foreach ([
            6
        ] as $value) {
            $this->assertFalse($validator->validateRule('max', $value));
        }
    }

    public function test_min()
    {
        $validator = $this->createIntValidator()
            ->min(5);

        foreach ([
            null,
            5,
            6
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            4
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }

        $validator = $this->createIntValidator()
            ->filled()
            ->min(5);

        foreach ([
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        $validator = $this->createIntValidator()
            ->min(0);

        foreach ([
            0,
            .0,
            0.0,
            0.1
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            -.1,
            -1
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }
    }

    protected function createIntValidator(): IntValidator
    {
        return (new Container())
            ->create(IntValidator::class);
    }
}
