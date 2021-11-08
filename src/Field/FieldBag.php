<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\DB\TypeClassMap;
use Closure;

/**
 * @method Field get(string $name)
 * @method Field[] getEntries()
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

    public function getRelation(string $name, Closure $callback = null): Relation
    {
        return $this->get($name, $callback);
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

        $this->container->get(function (TypeClassMap $typeClassMap) use ($RelatedTypeClass) {
            $typeClassMap->add($RelatedTypeClass::type(), $RelatedTypeClass);
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
            if (!$this->has($name)) {
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
                    $this->set($name, $relation);
                    continue;
                }
            }
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
