<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Test\ApiBuilder;
use PHPUnit\Framework\TestCase;

class ApiBuilderTest extends TestCase
{
    public function test_creates_different_apis()
    {
        $api = (new ApiBuilder())->api('Api')->get();
        $api2 = (new ApiBuilder())->api('Api2')->get();

        $this->assertEquals('Api', $api::type());
        $this->assertEquals('Api2', $api2::type());
    }

    public function test_creates_different_apis2()
    {
        $api = (new ApiBuilder())->api('Api')->get();
        $api2 = (new ApiBuilder())->api('Api')->get();

        $this->assertNotEquals($api, $api2);
    }
}
