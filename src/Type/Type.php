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

    protected FieldBag $updateFields;

    protected FieldBag $createFields;

    public static function list($TypeClassOrClassesOrMeta): TypeMeta
    {
        return (new TypeMeta())
            ->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta)
            ->list();
    }

    public static function link($TypeClassOrClassesOrMeta): TypeMeta
    {
        return (new TypeMeta())
            ->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta)
            ->link();
    }

    public function created(): void
    {
        $this->fields = $this->container
            ->create(FieldBag::class)
            ->owner($this);
        $this->fields($this->fields);

        $this->updateFields = $this->container
            ->create(FieldBag::class)
            ->owner($this)
            ->isMutation();
        $this->updateFields($this->updateFields);

        $this->createFields = $this->container
            ->create(FieldBag::class)
            ->owner($this)
            ->isMutation();
        $this->createFields($this->createFields, $this->updateFields);
    }

    public function hasField(string $name): bool
    {
        return $this->fields->has($name);
    }

    public function getFields(): FieldBag
    {
        return $this->fields;
    }

    public function getAllRelatedTypeClasses(): array
    {
        $TypeClasses = [];

        foreach ($this->fields->getEntries() as $field) {
            if ($field instanceof Relation) {
                $TypeClasses = [...$TypeClasses, ...$field->getRelatedType()->getAllTypeClasses()];
            }
        }

        return $TypeClasses;
    }

    public function getAllValidatorClasses(): array
    {
        $ValidatorClasses = [];

        foreach ($this->fields->getEntries() as $field) {
            if ($ValidatorClass = $field->getValidatorClass()) {
                $ValidatorClasses[] = $ValidatorClass;
            }
        }

        foreach ($this->updateFields->getEntries() as $field) {
            if ($ValidatorClass = $field->getValidatorClass()) {
                $ValidatorClasses[] = $ValidatorClass;
            }
        }

        foreach ($this->createFields->getEntries() as $field) {
            if ($ValidatorClass = $field->getValidatorClass()) {
                $ValidatorClasses[] = $ValidatorClass;
            }
        }

        return $ValidatorClasses;
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
        return $this->updateFields;
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
        return $this->createFields;
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
            'fields' => $this->fields->toSchemaJson(),
            'update_fields' => $this->updateFields->toSchemaJson(),
            'create_fields' => $this->createFields->toSchemaJson()
        ];
    }

    protected function fields(FieldBag $fields): void
    {
    }

    protected function updateFields(FieldBag $updateFields): void
    {
    }

    protected function createFields(FieldBag $createFields, FieldBag $updateFields): void
    {
    }
}
