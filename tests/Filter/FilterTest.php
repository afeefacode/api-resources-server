<?php

namespace Afeefa\ApiResources\Tests\Filter;

use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Filter\Filter;
use Afeefa\ApiResources\Test\FilterBuilder;
use Error;
use PHPUnit\Framework\TestCase;

class FilterTest extends TestCase
{
    public function test_options()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertFalse($filter->hasOption('my_option'));
        $this->assertEquals([], $filter->getOptions());

        $filter->options(['one', 'two']);
        $this->assertTrue($filter->hasOption('one'));
        $this->assertEquals(['one', 'two'], $filter->getOptions());

        $filter->options(['my_option', 'your_option']);
        $this->assertTrue($filter->hasOption('my_option'));
        $this->assertEquals(['my_option', 'your_option'], $filter->getOptions());

        $filter->options([]);
        $this->assertFalse($filter->hasOption('my_option'));
        $this->assertEquals([], $filter->getOptions());
    }

    public function test_default()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertFalse($filter->hasDefaultValue());
        $this->assertNull($filter->getDefaultValue());

        $filter->default('my_default');
        $this->assertTrue($filter->hasDefaultValue());
        $this->assertEquals('my_default', $filter->getDefaultValue());
    }

    public function test_default_null()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertFalse($filter->hasDefaultValue());
        $this->assertNull($filter->getDefaultValue());

        $filter->default('my_default');
        $this->assertTrue($filter->hasDefaultValue());
        $this->assertEquals('my_default', $filter->getDefaultValue());

        $filter->default(null);
        $this->assertFalse($filter->hasDefaultValue());
        $this->assertNull($filter->getDefaultValue());
    }

    public function test_null_is_option()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertFalse($filter->hasNullAsOption());

        $filter->nullIsOption();
        $this->assertTrue($filter->hasNullAsOption());

        $filter->nullIsOption(false);
        $this->assertFalse($filter->hasNullAsOption());

        $filter->nullIsOption(true);
        $this->assertTrue($filter->hasNullAsOption());
    }

    public function test_has_option()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $filter->options([true, false]);

        $this->assertTrue($filter->hasOption(true));
        $this->assertTrue($filter->hasOption(false));

        $this->assertFalse($filter->hasOption('test'));
        $this->assertFalse($filter->hasOption(null));
        $this->assertFalse($filter->hasNullAsOption());
    }

    public function test_options_with_null_auto_allows_null()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertFalse($filter->hasNullAsOption());

        $filter->options([null]);
        $this->assertTrue($filter->hasNullAsOption());

        $filter->options(['test']);
        $this->assertFalse($filter->hasNullAsOption());
    }

    public function test_missing_name()
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Typed property Afeefa\ApiResources\Filter\Filter::$name must not be accessed before initialization');

        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $this->assertNull($filter->getName());
    }

    public function test_name()
    {
        $filter = (new FilterBuilder())->filter('Test.Filter')->get();

        $filter->name('hans');
        $this->assertEquals('hans', $filter->getName());
    }

    public function test_setup()
    {
        $filter = (new FilterBuilder())->filter(
            'Test.Filter',
            function (Filter $filter) {
                $filter
                    ->name('hans')
                    ->options(['test'])
                    ->default('my_default');
            }
        )->get();

        $this->assertEquals('hans', $filter->getName());
        $this->assertEquals(['test'], $filter->getOptions());
        $this->assertEquals('my_default', $filter->getDefaultValue());
    }

    public function test_get_type_with_missing_type()
    {
        $this->expectException(MissingTypeException::class);
        $this->expectExceptionMessageMatches('/^Missing type for class Afeefa\\\ApiResources\\\Test\\\TestFilter@anonymous/');

        $filter = (new FilterBuilder())
            ->filter()
            ->get();

        $filter::type();
    }
}
