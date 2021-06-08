<?php

namespace Afeefa\ApiResources\Tests\DI;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\Bag\NotABagEntryException;
use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Exception\Exceptions\NotATypeOrCallbackException;
use PHPUnit\Framework\TestCase;

class BagTest extends TestCase
{
    public function test_bag()
    {
        $bag = new Bag();

        $this->assertFalse($bag->has('one'));

        $entry = new TestBagEntry([
            'value' => 'one'
        ]);

        $bag->set('one', $entry);

        $this->assertTrue($bag->has('one'));
        $this->assertFalse($bag->has('two'));

        $this->assertSame($bag->get('one'), $entry);

        $expected = [
            'one' => [
                'value' => 'one'
            ]
        ];
        $this->assertEquals($expected, $bag->toSchemaJson());
    }

    public function test_get_nonexisting()
    {
        $this->expectException(NotABagEntryException::class);
        $this->expectExceptionMessage('one is not a known Bag entry.');

        $bag = new Bag();

        $bag->get('one');
    }

    public function test_remove()
    {
        $bag = new Bag();

        $this->assertFalse($bag->has('one'));
        $this->assertFalse($bag->has('two'));

        $entry = new TestBagEntry([
            'value' => 'one'
        ]);

        $entry2 = new TestBagEntry([
            'value' => 'one2'
        ]);

        $bag->set('one', $entry);
        $bag->set('two', $entry2);

        $this->assertTrue($bag->has('one'));
        $this->assertTrue($bag->has('two'));

        $bag->remove('one');

        $this->assertFalse($bag->has('one'));
    }

    public function test_remove_nonexisting()
    {
        $this->expectException(NotABagEntryException::class);
        $this->expectExceptionMessage('one is not a known Bag entry.');

        $bag = new Bag();

        $bag->remove('one');
    }

    public function test_set_non_bagentry()
    {
        $message = preg_quote('Argument 2 passed to Afeefa\ApiResources\Bag\Bag::set() must implement interface Afeefa\ApiResources\Bag\BagEntryInterface, string given');
        $this->expectExceptionMessageMatches("/{$message}/");

        $bag = new Bag();

        $test = 'hoho';
        $a = 'test';

        $bag->set('one', $$a);
    }

    public function test_set_definition_with_type()
    {
        $container = new Container();
        $bag = $container->create(Bag::class);
        $bag->setDefinition('one', TestBagEntry::class, function (TestBagEntry $entry) {
            $entry->toJson([
                'value' => 'one'
            ]);
        });

        $this->assertTrue($bag->has('one'));

        $bag->get('one');

        $this->assertTrue($bag->has('one'));

        $expected = [
            'one' => [
                'value' => 'one'
            ]
        ];
        $this->assertEquals($expected, $bag->toSchemaJson());
    }

    public function test_set_definition_with_callback()
    {
        $container = new Container();
        $bag = $container->create(Bag::class);
        $bag->setDefinition('one', function (TestBagEntry $entry) {
            $entry->toJson([
                'value' => 'one'
            ]);
        });

        $this->assertTrue($bag->has('one'));

        $bag->get('one');

        $this->assertTrue($bag->has('one'));

        $expected = [
            'one' => [
                'value' => 'one'
            ]
        ];
        $this->assertEquals($expected, $bag->toSchemaJson());
    }

    public function test_set_definition_with_entries()
    {
        $container = new Container();
        $bag = $container->create(Bag::class);
        $bag->setDefinition('one', function (TestBagEntry $entry) {
            $entry->toJson([
                'value' => 'one'
            ]);
        });

        $bag->getEntries();

        $this->assertTrue($bag->has('one'));

        $expected = [
            'one' => [
                'value' => 'one'
            ]
        ];
        $this->assertEquals($expected, $bag->toSchemaJson());
    }

    public function test_set_definition_invalid_not_a_callback()
    {
        $this->expectException(NotATypeOrCallbackException::class);
        $this->expectExceptionMessage('Argument is not a type nor a valid callback.');

        $container = new Container();
        $bag = $container->create(Bag::class);
        $bag->setDefinition('one', new TestBagEntry('one'));
        $bag->get('one');
    }

    public function test_set_definition_invalid_not_a_bagentry()
    {
        $message = preg_quote('Argument 1 passed to Afeefa\ApiResources\Bag\Bag') . '.*' . preg_quote('must implement interface Afeefa\ApiResources\Bag\BagEntryInterface, instance of class@anonymous given');
        $this->expectExceptionMessageMatches("/{$message}/");

        $container = new Container();
        $bag = $container->create(Bag::class);

        $NoBagEntry = new class() {};

        $bag->setDefinition('one', get_class($NoBagEntry));
        $bag->get('one');
    }

    public function test_remove_definition()
    {
        $container = new Container();
        $bag = $container->create(Bag::class);
        $bag->setDefinition('one', TestBagEntry::class, function (TestBagEntry $entry) {
            $entry->toJson([
                'value' => 'one'
            ]);
        });

        $this->assertTrue($bag->has('one'));

        $bag->remove('one');

        $this->assertFalse($bag->has('one'));
    }
}

class TestBagEntry extends BagEntry
{
    public function __construct($toJson = null)
    {
        $this->toJson = $toJson;
    }

    public function toJson($toJson)
    {
        $this->toJson = $toJson;
    }

    public function toSchemaJson(): array
    {
        return $this->toJson;
    }
};
