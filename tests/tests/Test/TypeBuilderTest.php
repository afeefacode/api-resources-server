<?php

namespace Afeefa\ApiResources\Tests\Test;

use Afeefa\ApiResources\Test\ApiResourcesTest;
use function Afeefa\ApiResources\Test\T;

use Afeefa\ApiResources\Type\Type;

class TypeBuilderTest extends ApiResourcesTest
{
    public function test_creates_different_types()
    {
        $type = $this->typeBuilder()->type('Type')->get();
        $type2 = $this->typeBuilder()->type('Type2')->get();

        $this->assertEquals('Type', $type::type());
        $this->assertEquals('Type2', $type2::type());
    }

    public function test_creates_different_types2()
    {
        $type = $this->typeBuilder()->type('Type')->get();
        $type2 = $this->typeBuilder()->type('Type')->get();

        $this->assertNotEquals($type, $type2);
    }

    public function test_creates_container_entry()
    {
        $this->assertNull(T('Type', false));

        $type = $this->typeBuilder()->type('Type')->get();

        $this->assertEquals($type::class, T('Type'));
    }

    public function test_creates_different_container_entries_for_same_type_name()
    {
        $this->assertNull(T('Type', false));
        $this->assertEquals([], $this->getTypesInContainer());

        $type = $this->typeBuilder()->type('Type')->get();
        $type2 = $this->typeBuilder()->type('Type')->get();

        $typesInContainer = $this->getTypesInContainer();

        $this->assertCount(2, $typesInContainer);
        $this->assertEquals($type::class, $typesInContainer[0]);
        $this->assertEquals($type2::class, $typesInContainer[1]);

        // T() returns first type stored
        $this->assertEquals($type::class, T('Type'));
    }

    private function getTypesInContainer(): array
    {
        $typesInContainer = [];
        $entries = $this->container->entries();
        foreach (array_keys($entries) as $Class) {
            if (is_subclass_of($Class, Type::class)) {
                $typesInContainer[] = $Class;
            }
        }
        return $typesInContainer;
    }
}
