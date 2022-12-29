<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\StringValidator;
use PHPUnit\Framework\TestCase;

class StringValidatorTest extends TestCase
{
    public function test_default_string()
    {
        $validator = $this->createStringValidator();

        foreach ([
            null,
            '',
            'test',
            'and',
            'if',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validateRule('string', $value));
        }

        foreach ([
            1111,
            [],
            $this
        ] as $value) {
            $this->assertFalse($validator->validateRule('string', $value));
        }
    }

    public function test_sanitize()
    {
        $validator = $this->createStringValidator();
        $this->assertEquals('aaa', $validator->sanitize('aaa '));
        $this->assertEquals('a a', $validator->sanitize('  a  a '));
        $this->assertNull($validator->sanitize(''));

        $validator = $this->createStringValidator()
            ->trim(false)
            ->emptyNull(false)
            ->collapseWhite(false);

        $this->assertEquals('aaa ', $validator->sanitize('aaa '));
        $this->assertEquals('  a  a ', $validator->sanitize('  a  a '));
        $this->assertEquals('', $validator->sanitize(''));
    }

    public function test_filled()
    {
        $validator = $this->createStringValidator()
            ->filled();

        foreach ([
            'a',
            'and'
        ] as $value) {
            $this->assertTrue($validator->validateRule('filled', $value));
        }

        foreach ([
            '',
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('filled', $value));
        }
    }

    public function test_null()
    {
        $validator = $this->createStringValidator();

        foreach ([
            'a',
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('null', $value));
        }

        $validator = $this->createStringValidator()
            ->null(false);

        foreach ([
            null
        ] as $value) {
            $this->assertFalse($validator->validateRule('null', $value));
        }
    }

    public function test_min()
    {
        $validator = $this->createStringValidator()
            ->min(3);

        foreach ([
            '',
            'and',
            'test',
            '1111',
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            '',
            'and',
            'test',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            'a',
            'if'
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }
    }

    public function test_min_null()
    {
        $validator = $this->createStringValidator()
            ->min(3);

        foreach ([
            '', // filled not required
            'and',
            'test',
            '1111',
            null
        ] as $value) {
            $this->assertTrue($validator->validateRule('min', $value));
        }

        foreach ([
            'a',
            'if'
        ] as $value) {
            $this->assertFalse($validator->validateRule('min', $value));
        }
    }

    public function test_min_filled()
    {
        $validator = $this->createStringValidator()
            ->min(3)
            ->filled();

        foreach ([
            'and',
            'test',
            '1111'
        ] as $value) {
            $this->assertTrue($validator->validate($value));
        }

        foreach ([
            '',
            'a',
            'if',
            null
        ] as $value) {
            $this->assertFalse($validator->validate($value));
        }
    }

    public function test_max()
    {
        $validator = $this->createStringValidator()
            ->max(5);

        foreach ([
            '', // filled not required
            'test',
            'value'
        ] as $value) {
            $this->assertTrue($validator->validateRule('max', $value));
        }

        foreach ([
            'assert',
            'assertion'
        ] as $value) {
            $this->assertFalse($validator->validateRule('max', $value));
        }
    }

    public function test_regex()
    {
        $validator = $this->createStringValidator()
            ->regex('/test/');

        foreach ([
            'test',
            'a test',
            'test b',
            'a test b'
        ] as $value) {
            $this->assertTrue($validator->validateRule('regex', $value));
        }

        foreach ([
            'assert',
            'assertion'
        ] as $value) {
            $this->assertFalse($validator->validateRule('regex', $value));
        }
    }

    protected function createStringValidator(): StringValidator
    {
        return (new Container())
            ->create(StringValidator::class);
    }
}
