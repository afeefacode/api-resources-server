<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\ValidationFailedException;

class MutationResolveContext implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected Type $type;

    protected ?ModelInterface $owner = null;

    protected ?array $fieldsToSave = null;

    /**
     * @var MutationRelationResolver[]
     */
    protected ?array $relationResolvers = null;

    public function type(Type $type): MutationResolveContext
    {
        $this->type = $type;
        return $this;
    }

    public function owner(?ModelInterface $owner = null): MutationResolveContext
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOperation(): string
    {
        return isset($this->fieldsToSave['id']) ? Operation::UPDATE : Operation::CREATE;
    }

    public function fieldsToSave(?array $fields): MutationResolveContext
    {
        $this->fieldsToSave = $fields;
        return $this;
    }

    /**
     * @return MutationRelationResolver[]
     */
    public function getRelationResolvers(): array
    {
        if (!$this->relationResolvers) {
            $this->relationResolvers = $this->createRelationResolvers();
        }
        return $this->relationResolvers;
    }

    public function getSaveFields(array $additionalFields = []): array
    {
        $saveFields = $this->calculateSaveFields();
        return array_merge($saveFields, $additionalFields);
    }

    protected function createRelationResolvers(): array
    {
        $type = $this->type;
        $fieldsToSave = $this->fieldsToSave;
        $operation = $this->getOperation();

        $relationResolvers = [];
        foreach ($fieldsToSave as $fieldName => $value) {
            if ($this->hasSaveRelation($type, $operation, $fieldName)) {
                $relation = $this->getSaveRelation($type, $operation, $fieldName);

                if ($relation->isList()) {
                    if (!is_array($value)) {
                        throw new ValidationFailedException("Value passed to the many relation {$fieldName} must be an array.");
                    }
                } else {
                    if (!is_array($value) && $value !== null) {
                        throw new ValidationFailedException("Value passed to the singular relation {$fieldName} must be null or an array.");
                    }
                }

                $resolveCallback = $relation->getSaveResolve();

                if ($resolveCallback) {
                    /** @var MutationRelationResolver */
                    $mutationRelationResolver = null;

                    $this->container->call(
                        $resolveCallback,
                        function (DependencyResolver $r) {
                            if ($r->isOf(MutationRelationResolver::class)) {
                                $r->create();
                            }
                        },
                        function () use (&$mutationRelationResolver) {
                            $arguments = func_get_args();
                            foreach ($arguments as $argument) {
                                if ($argument instanceof MutationRelationResolver) {
                                    $mutationRelationResolver = $argument;
                                }
                            }
                        }
                    );

                    if (!$mutationRelationResolver) {
                        throw new InvalidConfigurationException("Resolve callback for save relation {$fieldName} on type {$type::type()} must receive a MutationRelationResolver as argument.");
                    }

                    $mutationRelationResolver
                        ->relation($relation)
                        ->fieldsToSave($value);

                    if ($this->owner) {
                        $mutationRelationResolver->addOwner($this->owner);
                    }

                    $relationResolvers[$fieldName] = $mutationRelationResolver;
                } else {
                    throw new InvalidConfigurationException("Relation {$fieldName} on type {$type::type()} does not have a relation resolver.");
                }
            }
        }

        return $relationResolvers;
    }

    protected function calculateSaveFields(): array
    {
        $type = $this->type;
        $fieldsToSave = $this->fieldsToSave;
        $operation = $this->getOperation();

        $saveFields = [];

        if (is_array($fieldsToSave)) {
            foreach ($fieldsToSave as $fieldName => $value) {
                // value is a scalar
                if ($this->hasSaveAttribute($type, $operation, $fieldName)) {
                    $attribute = $this->getSaveAttribute($type, $operation, $fieldName);
                    if (!$attribute->hasSaveResolver()) { // let resolvers provide value
                        $saveFields[$fieldName] = $value;
                    }
                }
            }
        }

        return $saveFields;
    }

    protected function hasSaveAttribute(Type $type, string $operation, string $name): bool
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'has' . $method . 'Attribute'}($name);
    }

    protected function getSaveAttribute(Type $type, string $operation, string $name): Attribute
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'get' . $method . 'Attribute'}($name);
    }

    protected function hasSaveRelation(Type $type, string $operation, string $name): bool
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'has' . $method . 'Relation'}($name);
    }

    protected function getSaveRelation(Type $type, string $operation, string $name): Relation
    {
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';
        return $type->{'get' . $method . 'Relation'}($name);
    }
}
