<?php

namespace Afeefa\ApiResources\Tests\Test;

use Afeefa\ApiResources\Test\ValidatorBuilder;
use PHPUnit\Framework\TestCase;

class ValidatorBuilderTest extends TestCase
{
    public function test_creates_different_validators()
    {
        $validator = (new ValidatorBuilder())->Validator('Validator')->get();
        $validator2 = (new ValidatorBuilder())->Validator('Validator2')->get();

        $this->assertEquals('Validator', $validator::type());
        $this->assertEquals('Validator2', $validator2::type());
    }

    public function test_creates_different_validators2()
    {
        $validator = (new ValidatorBuilder())->Validator('Validator')->get();
        $validator2 = (new ValidatorBuilder())->Validator('Validator')->get();

        $this->assertNotEquals($validator, $validator2);
    }
}
