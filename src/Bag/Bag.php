<?php

namespace Afeefa\ApiResources\Bag;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Closure;

class Bag implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;

    /**
     * @var BagEntryInterface[]
     */
    private array $entries = [];

    public function get(string $name, Closure $callback = null): BagEntryInterface
    {
        $entry = $this->entries[$name];

        if ($callback) {
            $callback($entry);
        }

        return $entry;
    }

    public function set(string $name, $value): Bag
    {
        $this->entries[$name] = $value;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$name]);
    }

    public function remove(string $name): Bag
    {
        unset($this->entries[$name]);
        return $this;
    }

    public function entries(): array
    {
        return $this->entries;
    }

    public function getSchemaJson(): array
    {
        return array_filter(array_map(function (BagEntryInterface $entry) {
            if (method_exists($this, 'getEntrySchemaJson')) {
                return $this->container->call(
                    [$this, 'getEntrySchemaJson'],
                    function (DependencyResolver $r) use ($entry) {
                        if ($r->isOf(BagEntryInterface::class)) {
                            $r->fix($entry);
                        }
                    }
                );
            }
            return $entry->toSchemaJson();
        }, $this->entries));
    }
}
