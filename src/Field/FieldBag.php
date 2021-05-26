<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\Bag\Bag;
use Closure;

/**
 * @method Field get(string $name)
 * @method Field[] entries()
 */
class FieldBag extends Bag
{
    protected ?FieldBag $original = null;

    public function original(FieldBag $fieldBag): FieldBag
    {
        $this->original = $fieldBag;
        return $this;
    }

    public function get(string $name, Closure $callback = null): Field
    {
        if ($this->original && !$this->has($name)) {
            $field = $this->original->get($name)->clone();
            $this->set($name, $field);
        }

        return parent::get($name, $callback);
    }

    public function attribute(string $name, $classOrCallback): FieldBag
    {
        $this->container->create($classOrCallback, function (Attribute $attribute) use ($name) {
            $attribute
                ->name($name)
                ->allowed(true);
            $this->set($name, $attribute);
        });

        return $this;
    }

    public function relation(string $name, string $RelatedTypeClass, $classOrCallback): FieldBag
    {
        $this->container->create($classOrCallback, function (Relation $relation) use ($name, $RelatedTypeClass) {
            $relation
                ->name($name)
                ->allowed(true)
                ->relatedTypeClass($RelatedTypeClass);
            $this->set($name, $relation);
        });

        return $this;
    }

    public function allow(array $names): FieldBag
    {
        foreach ($names as $name) {
            $this->get($name)->allowed(true);
        }
        return $this;
    }

    public function getEntrySchemaJson(Field $field, TypeRegistry $typeRegistry): ?array
    {
        $typeRegistry->registerField(get_class($field));
        if ($field->isAllowed()) {
            return $field->toSchemaJson();
        }
        return null;
    }
}
