<?php

namespace Afeefa\ApiResources\Type;

use Afeefa\ApiResources\Api\ToSchemaJsonInterface;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\Field;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;

class Type implements ToSchemaJsonInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    public static string $type = 'Afeefa.Type';

    protected FieldBag $fields;

    public static function list(string $TypeClass): TypeMeta
    {
        return (new TypeMeta())->typeClass($TypeClass)->list();
    }

    public static function create(string $TypeClass): TypeMeta
    {
        return (new TypeMeta())->typeClass($TypeClass)->create();
    }

    public static function update(string $TypeClass): TypeMeta
    {
        return (new TypeMeta())->typeClass($TypeClass)->update();
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

    public function getField(string $name): Field
    {
        return $this->fields->get($name);
    }

    public function hasAttribute(string $name): bool
    {
        return $this->hasField($name) && $this->getField($name) instanceof Attribute;
    }

    public function getAttribute(string $name): Attribute
    {
        return $this->getField($name);
    }

    public function hasRelation(string $name): bool
    {
        return $this->hasField($name) && $this->getField($name) instanceof Relation;
    }

    public function getRelation(string $name): Relation
    {
        return $this->getField($name);
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
