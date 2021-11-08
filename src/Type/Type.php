<?php

namespace Afeefa\ApiResources\Type;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\Field;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;

class Type implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use HasStaticTypeTrait;
    use ContainerAwareTrait;

    protected FieldBag $fields;

    public static function list($TypeClassOrClasses): TypeMeta
    {
        $meta = new TypeMeta();
        $meta->typeClassOrClasses($TypeClassOrClasses);
        return $meta->list();
    }

    public static function create(string $TypeClass): TypeMeta
    {
        return (new TypeMeta())->typeClassOrClasses($TypeClass)->create();
    }

    public static function update(string $TypeClass): TypeMeta
    {
        return (new TypeMeta())->typeClassOrClasses($TypeClass)->update();
    }

    public function created(): void
    {
        $this->fields = $this->container->create(FieldBag::class);
        $this->fields($this->fields);
    }

    public function hasField(string $name): bool
    {
        return $this->fields->has($name);
    }

    public function getFields(): FieldBag
    {
        return $this->fields;
    }

    public function getField(string $name): Field
    {
        return $this->fields->get($name);
    }

    public function hasUpdateField(string $name): bool
    {
        return $this->getUpdateFields()->has($name);
    }

    public function getUpdateFields(): FieldBag
    {
        return $this->fields;
    }

    public function getUpdateField(string $name): Field
    {
        return $this->getUpdateFields()->get($name);
    }

    public function hasCreateField(string $name): bool
    {
        return $this->getCreateFields()->has($name);
    }

    public function getCreateFields(): FieldBag
    {
        return $this->fields;
    }

    public function getCreateField(string $name): Field
    {
        return $this->getCreateFields()->get($name);
    }

    public function hasAttribute(string $name): bool
    {
        return $this->hasField($name) && $this->getField($name) instanceof Attribute;
    }

    public function getAttribute(string $name): ?Attribute
    {
        $field = $this->getField($name);
        if ($field instanceof Attribute) {
            return $field;
        }
        return null;
    }

    public function hasUpdateAttribute(string $name): bool
    {
        return $this->hasUpdateField($name) && $this->getUpdateField($name) instanceof Attribute;
    }

    public function getUpdateAttribute(string $name): ?Attribute
    {
        $field = $this->getUpdateField($name);
        if ($field instanceof Attribute) {
            return $field;
        }
        return null;
    }

    public function hasCreateAttribute(string $name): bool
    {
        return $this->hasCreateField($name) && $this->getCreateField($name) instanceof Attribute;
    }

    public function getCreateAttribute(string $name): ?Attribute
    {
        $field = $this->getCreateField($name);
        if ($field instanceof Attribute) {
            return $field;
        }
        return null;
    }

    public function hasRelation(string $name): bool
    {
        return $this->hasField($name) && $this->getField($name) instanceof Relation;
    }

    public function getRelation(string $name): ?Relation
    {
        $field = $this->getField($name);
        if ($field instanceof Relation) {
            return $field;
        }
        return null;
    }

    public function hasUpdateRelation(string $name): bool
    {
        return $this->hasUpdateField($name) && $this->getUpdateField($name) instanceof Relation;
    }

    public function getUpdateRelation(string $name): ?Relation
    {
        $field = $this->getUpdateField($name);
        if ($field instanceof Relation) {
            return $field;
        }
        return null;
    }

    public function hasCreateRelation(string $name): bool
    {
        return $this->hasCreateField($name) && $this->getCreateField($name) instanceof Relation;
    }

    public function getCreateRelation(string $name): ?Relation
    {
        $field = $this->getCreateField($name);
        if ($field instanceof Relation) {
            return $field;
        }
        return null;
    }

    public function toSchemaJson(): array
    {
        return [
            'translations' => $this->translations(),
            'fields' => $this->fields->toSchemaJson()
        ];
    }

    protected function translations(): array
    {
        return [];
    }

    protected function fields(FieldBag $fields): void
    {
    }
}
