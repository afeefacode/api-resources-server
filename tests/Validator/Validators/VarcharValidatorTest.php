<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\VarcharValidator;
use PHPUnit\Framework\TestCase;

class VarcharValidatorTest extends TestCase
{
    public function test_default_string()
    {
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class);

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
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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

    public function test_min()
    {
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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
        /** @var VarcharValidator */
        $validator = (new Container())
            ->create(VarcharValidator::class)
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
