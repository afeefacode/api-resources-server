<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;
use Closure;

/**
 * @method Field get(string $name, Closure $callback)
 * @method Field[] getEntries()
 */
class FieldBag extends Bag
{
    protected $owner;

    protected bool $isMutation = false;

    public function owner($owner): FieldBag
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function isMutation(bool $isMutation = true): FieldBag
    {
        $this->isMutation = $isMutation;
        return $this;
    }

    public function getAttribute(string $name, Closure $callback = null): Attribute
    {
        return $this->get($name, $callback);
    }

    public function getRelation(string $name, Closure $callback = null): Relation
    {
        return $this->get($name, $callback);
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
                ->isMutation($this->isMutation);
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
                ->isMutation($this->isMutation)
                ->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta);
            $this->setInternal($name, $relation);
        });

        return $this;
    }

    public function from(FieldBag $fromFields, string $name, Closure $callback = null): FieldBag
    {
        $field = $fromFields->get($name)->clone();
        $this->setInternal($name, $field);
        if ($callback) {
            $callback($field);
        }
        return $this;
    }

    public function getEntrySchemaJson(Field $field): ?array
    {
        return $field->toSchemaJson();
    }
}
