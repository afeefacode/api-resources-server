<?php

namespace Afeefa\ApiResources\Tests\Api;

use Afeefa\ApiResources\Test\TypeRegistry;
use Afeefa\ApiResources\Type\Type;
use PHPUnit\Framework\TestCase;

class TypeRegistryTest extends TestCase
{
    public function test_creates_type_once()
    {
        $type = new class () extends Type {
            protected static string $type = 'TESTTYPE';
            public static int $count = 0;

            public function __construct()
            {
                static::$count++;
            }
        };

        TypeRegistry::reset();
        TypeRegistry::register($type);

        $type1 = TypeRegistry::getOrCreate('TESTTYPE');
        $type1_b = TypeRegistry::getOrCreate('TESTTYPE');

        $this->assertSame($type1, $type1_b);

        $type2 = TypeRegistry::getOrCreate('TESTTYPE_DYNAMIC');
        $type2_b = TypeRegistry::getOrCreate('TESTTYPE_DYNAMIC');

        $this->assertSame($type2, $type2_b);

        $this->assertEquals(2, TypeRegistry::count());
    }
}
