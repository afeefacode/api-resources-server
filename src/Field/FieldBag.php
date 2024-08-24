<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Bag\Bag;
use Afeefa\ApiResources\Bag\BagEntryInterface;
use Afeefa\ApiResources\Field\Fields\BooleanAttribute;
use Afeefa\ApiResources\Field\Fields\DateAttribute;
use Afeefa\ApiResources\Field\Fields\EnumAttribute;
use Afeefa\ApiResources\Field\Fields\IntAttribute;
use Afeefa\ApiResources\Field\Fields\NumberAttribute;
use Afeefa\ApiResources\Field\Fields\SetAttribute;
use Afeefa\ApiResources\Field\Fields\StringAttribute;
use Afeefa\ApiResources\Type\Type;
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
        $this->_attribute($name, $classOrCallback);
        return $this;
    }

    public function string(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, StringAttribute::class, $callback, $validate);
    }

    public function date(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, DateAttribute::class, $callback, $validate);
    }

    public function boolean(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, BooleanAttribute::class, $callback, $validate);
    }

    public function enum(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, EnumAttribute::class, $callback, $validate);
    }

    public function enumset(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, SetAttribute::class, $callback, $validate);
    }

    public function int(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, IntAttribute::class, $callback, $validate);
    }

    public function number(string $name, Closure $callback = null, $validate = null): FieldBag
    {
        return $this->_attribute($name, NumberAttribute::class, $callback, $validate);
    }

    public function relation(string $name, $TypeClassOrClassesOrMeta, $classOrCallback = Relation::class): FieldBag
    {
        $this->_relation($name, $TypeClassOrClassesOrMeta, $classOrCallback);
        return $this;
    }

    public function hasOne(string $name, $TypeClassOrClasses, $classOrCallback = Relation::class, $validate = null): FieldBag
    {
        return $this->_relation($name, $TypeClassOrClasses, $classOrCallback, $validate);
    }

    public function hasMany(string $name, $TypeClassOrClasses, $classOrCallback = Relation::class, $validate = null): FieldBag
    {
        return $this->_relation($name, Type::list($TypeClassOrClasses), $classOrCallback, $validate);
    }

    public function linkOne(string $name, $TypeClassOrClasses, $classOrCallback = Relation::class, $validate = null): FieldBag
    {
        return $this->_relation($name, Type::link($TypeClassOrClasses), $classOrCallback, $validate);
    }

    public function linkMany(string $name, $TypeClassOrClasses, $classOrCallback = Relation::class, $validate = null): FieldBag
    {
        return $this->_relation($name, Type::list(Type::link($TypeClassOrClasses)), $classOrCallback, $validate);
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

    protected function _attribute(string $name, $classOrCallback, Closure $callback = null, $validate = null): FieldBag
    {
        $attribute = $this->container->create($classOrCallback, function (Attribute $attribute) use ($name, $validate) {
            $attribute
                ->owner($this->getOwner())
                ->name($name)
                ->isMutation($this->isMutation);

            if ($validate) {
                $attribute->validate($validate);
            }
            $this->setInternal($name, $attribute);
        });

        if ($callback) {
            $callback($attribute);
        }

        return $this;
    }

    protected function _relation(string $name, $TypeClassOrClassesOrMeta, $classOrCallback = Relation::class, $validate = null): FieldBag
    {
        $this->container->create($classOrCallback, function (Relation $relation) use ($name, $TypeClassOrClassesOrMeta, $validate) {
            $relation
                ->owner($this->getOwner())
                ->name($name)
                ->isMutation($this->isMutation)
                ->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta);

            if ($validate) {
                $relation->validate($validate);
            }
            $this->setInternal($name, $relation);
        });

        return $this;
    }
}
