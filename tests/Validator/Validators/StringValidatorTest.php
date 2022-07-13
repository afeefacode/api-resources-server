<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\StringValidator;
use PHPUnit\Framework\TestCase;

class StringValidatorTest extends TestCase
{
    public function test_default_string()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class);

        foreach ([
            '',
            'test',
            'and',
            'if',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            1111,
            [],
            $this,
            null
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_filled()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->filled();

        foreach ([
            'a',
            'and'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            '',
            null
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_null()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->null();

        foreach ([
            'a',
            null
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }
    }

    public function test_min()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->min(3);

        foreach ([
            '', // filled not required
            'test',
            'and',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            'a',
            'if',
            null // filled not required but null not allowed
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_min_null()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->min(3)
            ->null();

        foreach ([
            '', // filled not required
            'test',
            'and',
            '1111',
            null // null allowed and filled not required
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            'a',
            'if'
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_min_filled()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->min(3)
            ->filled();

        foreach ([
            'test',
            'and',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            '',
            'a',
            'if'
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_max()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->max(5);

        foreach ([
            '', // filled not required
            'test',
            'value'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            'assert',
            'assertion'
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_regex()
    {
        /** @var StringValidator */
        $validator = (new Container())
            ->create(StringValidator::class)
            ->regex('/test/');

        foreach ([
            'test',
            'a test',
            'test b',
            'a test b'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            'assert',
            'assertion'
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }
}
