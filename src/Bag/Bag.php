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

    private array $definitions = [];

    public function get(string $name, Closure $callback = null): BagEntryInterface
    {
        $entry = $this->entries[$name] ?? null;

        if (!$entry) {
            [$classOrCallback, $createCallback] = $this->definitions[$name] ?? [null, null];
            if (!$classOrCallback) {
                throw new NotABagEntryException("{$name} is not a known Bag entry.");
            }

            $entry = $this->container->create($classOrCallback, function (BagEntryInterface $entry) use ($name, $createCallback) {
                if ($createCallback) {
                    $createCallback($entry);
                }
                $this->setInternal($name, $entry);
            });
        }

        if ($callback) {
            $callback($entry);
        }

        return $entry;
    }

    public function setDefinition(string $name, $classOrCallback, Closure $createCallback = null): Bag
    {
        $this->definitions[$name] = [$classOrCallback, $createCallback];
        return $this;
    }

    public function set(string $name, BagEntryInterface $value): Bag
    {
        return $this->setInternal($name, $value);
    }

    public function has(string $name): bool
    {
        return $this->hasInternal($name);
    }

    public function remove(string $name): Bag
    {
        $entry = $this->entries[$name] ?? null;

        if (!$entry) {
            [$classOrCallback, $createCallback] = $this->definitions[$name] ?? [null, null];
            if (!$classOrCallback) {
                throw new NotABagEntryException("{$name} is not a known Bag entry.");
            }
        }

        unset($this->definitions[$name]);
        unset($this->entries[$name]);
        return $this;
    }

    public function getEntries(): array
    {
        // create entries from all definitions, if not existing
        foreach (array_keys($this->definitions) as $name) {
            $this->get($name);
        }

        return $this->entries;
    }

    public function numEntries(): int
    {
        return count($this->entries);
    }

    public function toSchemaJson(): array
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
        }, $this->getEntries()));
    }

    public function hasInternal(string $name): bool
    {
        return isset($this->entries[$name]) || isset($this->definitions[$name]);
    }

    protected function setInternal(string $name, BagEntryInterface $value, ?string $after = null): Bag
    {
        if ($after) {
            $this->entries = $this->insertAfter($after, $this->entries, $name, $value);
        } else {
            $this->entries[$name] = $value;
        }
        return $this;
    }

    protected function insertAfter($afterKey, array $array, $newKey, $newValue)
    {
        $new = [];
        $added = false;
        foreach ($array as $k => $value) {
            $new[$k] = $value;
            if ($k === $afterKey) {
                $new[$newKey] = $newValue;
                $added = true;
            }
        }
        if (!$added) {
            $new[$newKey] = $newValue;
        }
        return $new;
    }
}
