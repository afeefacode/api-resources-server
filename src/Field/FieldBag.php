<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;
use Closure;

/**
 * @method Field get(string $name)
 * @method Field[] getEntries()
 */
class FieldBag extends Bag
{
    protected $owner;

    protected ?FieldBag $original = null;

    public function owner($owner): FieldBag
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner()
    {
        if ($this->original) {
            return $this->original->getOwner();
        }

        return $this->owner;
    }

    public function original(FieldBag $fieldBag): FieldBag
    {
        $this->original = $fieldBag;
        return $this;
    }

    public function has(string $name, bool $ownFields = false): bool
    {
        if ($this->original && !$this->hasInternal($name) && !$ownFields) {
            return $this->original->has($name);
        }

        return parent::has($name);
    }

    public function get(string $name, Closure $callback = null): Field
    {
        if ($this->original && !$this->hasInternal($name)) {
            $field = $this->original->get($name)->clone();
            $this->setInternal($name, $field);
        }

        return parent::get($name, $callback);
    }

    public function getAttribute(string $name, Closure $callback = null): Attribute
    {
        return $this->get($name, $callback);
    }

    public function getRelation(string $name, Closure $callback = null): Relation
    {
        return $this->get($name, $callback);
    }

    public function getOriginal(): ?FieldBag
    {
        return $this->original;
    }

    /**
     * disabled
     */
    public function set(string $name, BagEntryInterface $value): Bag
    {
        return $this;
    }

    public function attribute(string $name, $classOrCallback): FieldBag
    {
        $this->container->create($classOrCallback, function (Attribute $attribute) use ($name) {
            $attribute
                ->owner($this->getOwner())
                ->name($name)
                ->allowed(true);
            $this->setInternal($name, $attribute);
        });

        return $this;
    }

    public function relation(string $name, $TypeClassOrClassesOrMeta, $classOrCallback = Relation::class): FieldBag
    {
        $this->container->create($classOrCallback, function (Relation $relation) use ($name, $TypeClassOrClassesOrMeta) {
            $relation
                ->owner($this->getOwner())
                ->name($name)
                ->allowed(true)
                ->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta);
            $this->setInternal($name, $relation);
        });

        return $this;
    }

    public function allow(array $names): FieldBag
    {
        // disallow own fields of this bag
        foreach (array_values($this->getEntries()) as $field) {
            if (!in_array($field->getName(), $names)) {
                $field->allowed(false);
            }
        }

        // allow all allowed fields
        foreach ($names as $name) {
            if (!$this->has($name)) { // check special relations
                if (preg_match('/^(.+)#(add|delete|update)$/', $name, $matches)) {
                    $baseRelationName = $matches[1];
                    $adds = $matches[2] === 'add';
                    $deletes = $matches[2] === 'delete';
                    $updates = $matches[2] === 'update';
                    $relation = $this->getRelation($baseRelationName)
                        ->clone()
                        ->allowed(true)
                        ->updatesItems($updates)
                        ->addsItems($adds)
                        ->deletesItems($deletes);
                    $this->setInternal($name, $relation);
                    continue;
                }
            }
            $this->get($name)->allowed(true);
        }
        return $this;
    }

    public function clone(): FieldBag
    {
        return $this->container->create(function (FieldBag $fieldBag) {
            $fieldBag->original($this);
        });
    }

    public function getEntrySchemaJson(Field $field): ?array
    {
        if ($field->isAllowed()) {
            return $field->toSchemaJson();
        }
        return null;
    }
}
