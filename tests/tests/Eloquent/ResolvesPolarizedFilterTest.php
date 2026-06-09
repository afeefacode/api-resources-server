<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Eloquent\ResolvesPolarizedFilter;
use PHPUnit\Framework\TestCase;

class ResolvesPolarizedFilterTest extends TestCase
{
    private function splitter(): object
    {
        return new class () {
            use ResolvesPolarizedFilter;

            public function split($value): array
            {
                return $this->splitPolarizedValue($value);
            }
        };
    }

    public function test_include_only()
    {
        [$include, $exclude] = $this->splitter()->split(['2', '4']);

        $this->assertEquals(['2', '4'], $include);
        $this->assertEquals([], $exclude);
    }

    public function test_include_and_exclude()
    {
        [$include, $exclude] = $this->splitter()->split(['2', '4', 'n-5', 'n-6']);

        $this->assertEquals(['2', '4'], $include);
        $this->assertEquals(['5', '6'], $exclude);
    }

    public function test_exclude_only()
    {
        [$include, $exclude] = $this->splitter()->split(['n-5']);

        $this->assertEquals([], $include);
        $this->assertEquals(['5'], $exclude);
    }

    public function test_string_id_after_prefix_survives()
    {
        // Split only at the first `n-` so an id like `none` survives.
        [$include, $exclude] = $this->splitter()->split(['n-none']);

        $this->assertEquals([], $include);
        $this->assertEquals(['none'], $exclude);
    }

    public function test_empty()
    {
        [$include, $exclude] = $this->splitter()->split([]);

        $this->assertEquals([], $include);
        $this->assertEquals([], $exclude);
    }

    public function test_scalar_value_is_cast_to_array()
    {
        [$include, $exclude] = $this->splitter()->split('2');

        $this->assertEquals(['2'], $include);
        $this->assertEquals([], $exclude);
    }
}
