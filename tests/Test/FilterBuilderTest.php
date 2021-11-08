<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Test\FilterBuilder;
use PHPUnit\Framework\TestCase;

class FilterBuilderTest extends TestCase
{
    public function test_creates_different_filters()
    {
        $filter = (new FilterBuilder())->Filter('Filter')->get();
        $filter2 = (new FilterBuilder())->Filter('Filter2')->get();

        $this->assertEquals('Filter', $filter::type());
        $this->assertEquals('Filter2', $filter2::type());
    }

    public function test_creates_different_filters2()
    {
        $filter = (new FilterBuilder())->Filter('Filter')->get();
        $filter2 = (new FilterBuilder())->Filter('Filter')->get();

        $this->assertNotEquals($filter, $filter2);
    }
}
