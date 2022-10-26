<?php

namespace Afeefa\ApiResources\Tests\Filter\Filters;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Filter\Filters\PageSizeFilter;
use PHPUnit\Framework\TestCase;

class PageSizeFilterTest extends TestCase
{
    public function test()
    {
        /** @var PageSizeFilter */
        $filter = (new Container())->create(PageSizeFilter::class);

        $this->assertEquals([15], $filter->getPageSizes());
        $this->assertEquals([15], $filter->getOptions());

        $this->assertTrue($filter->hasDefaultValue());
        $this->assertEquals(15, $filter->getDefaultValue());

        $this->assertTrue($filter->hasPageSize(15));
        $this->assertTrue($filter->hasOption(15));
        $this->assertFalse($filter->hasPageSize(5));
        $this->assertFalse($filter->hasOption(5));

        $filter->pageSizes([1, 2, 3]);

        $this->assertEquals([1, 2, 3], $filter->getPageSizes());

        $this->assertTrue($filter->hasDefaultValue());
        $this->assertEquals(15, $filter->getDefaultValue()); // still 15 but might be not selectable

        $this->assertTrue($filter->hasPageSize(1));
        $this->assertTrue($filter->hasOption(1));
        $this->assertFalse($filter->hasPageSize(5));
        $this->assertFalse($filter->hasOption(5));
    }
}
