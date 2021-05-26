<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Type\Type;

class TypeRegistry implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected array $validators = [];

    protected array $fields = [];

    protected array $relations = [];

    protected array $TypeClasses = [];

    protected array $resources = [];

    public function registerValidator(string $ValidatorClass)
    {
        $this->validators[$ValidatorClass] = $ValidatorClass;
    }

    public function validators()
    {
        return $this->validators;
    }

    public function registerField(string $FieldClass)
    {
        $this->fields[$FieldClass] = $FieldClass;
    }

    public function registerRelation(string $RelationClass)
    {
        $this->relations[$RelationClass] = $RelationClass;
    }

    public function registerType(string $TypeClass)
    {
        if (!isset($this->TypeClasses[$TypeClass])) {
            $this->container->get($TypeClass, function (Type $type) use ($TypeClass) {
                $this->TypeClasses[$TypeClass] = $TypeClass;
                $type->toSchemaJson();
            });
        }
    }

    public function getTypeClasses()
    {
        return $this->TypeClasses;
    }

    public function registerResource(string $ResourceClass)
    {
        $this->resources[$ResourceClass] = $ResourceClass;
    }

    public function dumpEntries()
    {
        debug_dump([
            'TypeClasses' => $this->TypeClasses,
            'fields' => $this->fields,
            'validators' => $this->validators,
            'relations' => $this->relations,
        ]);
    }
}
