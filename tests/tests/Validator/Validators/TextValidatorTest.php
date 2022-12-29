<?php

namespace Afeefa\ApiResources\Tests\Validator\Validators;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Validator\Validators\TextValidator;
use PHPUnit\Framework\TestCase;

class TextValidatorTest extends TestCase
{
    public function test_sanitize()
    {
        $validator = $this->createTextValidator();
        $this->assertEquals('aaa', $validator->sanitize('aaa '));
        $this->assertEquals('a  a', $validator->sanitize('  a  a '));
        $this->assertNull($validator->sanitize(''));

        $validator = $this->createTextValidator()
            ->trim(false)
            ->emptyNull(false)
            ->collapseWhite(true);

        $this->assertEquals('aaa ', $validator->sanitize('aaa '));
        $this->assertEquals(' a a ', $validator->sanitize('  a  a '));
        $this->assertEquals('', $validator->sanitize(''));
    }

    protected function createTextValidator(): TextValidator
    {
        return (new Container())
            ->create(TextValidator::class);
    }
}
