<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use function Afeefa\ApiResources\DI\invokeResolverCallback;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Field\FieldBag;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Validator\ValidationFailedException;
use Afeefa\ApiResources\Validator\Validator;

class MutationResolveContext implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected Type $type;

    protected ?ModelInterface $owner = null;

    protected ?string $operation = null;

    protected ?array $fieldsToSave = null;

    /**
     * @var MutationRelationResolver[]
     */
    protected array $relationResolvers = [];

    protected bool $relationResolversInitialized = false;

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

    public function operation(string $operation): self
    {
        $this->operation = $operation;
        return $this;
    }

    public function getOperation(): string
    {
        if ($this->operation) {
            return $this->operation;
        }
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
        if (!$this->relationResolversInitialized) {
            $this->relationResolvers = array_merge(
                $this->relationResolvers,
                $this->createRelationResolvers()
            );
            $this->relationResolversInitialized = true;
        }
        return $this->relationResolvers;
    }

    public function getRelationResolver(string $name): ?MutationRelationResolver
    {
        if (!array_key_exists($name, $this->relationResolvers)) {
            $this->relationResolvers[$name] = $this->createRelationResolver($name);
        }
        return $this->relationResolvers[$name];
    }

    public function getSaveFields(array $additionalFields = []): array
    {
        $saveFields = $this->calculateSaveFields();
        return array_merge($saveFields, $additionalFields);
    }

    public function getRequiredFieldNames(): array
    {
        $type = $this->type;
        $operation = $this->getOperation();
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';

        $fieldNames = [];

        /** @var FieldBag */
        $fieldBag = $type->{'get' . $method . 'Fields'}();
        foreach ($fieldBag->getEntries() as $field) {
            if ($field->isRequired()) {
                $fieldNames[] = $field->getName();
            }
        }

        return $fieldNames;
    }

    /**
     * @return Validator[]
     */
    public function getFieldValidators(): array
    {
        $type = $this->type;
        $operation = $this->getOperation();
        $method = $operation === Operation::UPDATE ? 'Update' : 'Create';

        $validators = [];

        if ($this->fieldsToSave) {
            /** @var FieldBag */
            $fieldBag = $type->{'get' . $method . 'Fields'}();
            foreach ($fieldBag->getEntries() as $name => $field) {
                if (array_key_exists($name, $this->fieldsToSave)) {
                    if ($field->hasValidator()) {
                        $validators[$name] = $field->getValidator();
                    }
                }
            }
        }

        return $validators;
    }

    protected function createRelationResolvers(): array
    {
        $type = $this->type;
        $fieldsToSave = $this->fieldsToSave ?: [];
        $operation = $this->getOperation();

        $relationResolvers = [];
        foreach ($fieldsToSave as $fieldNameWithOperation => $value) {
            // allow for relation#add or relation#delete, which are useful for (has|link)Many relations
            preg_match('/(\w+)(?:#(add|delete))?$/', $fieldNameWithOperation, $matches);
            $fieldName = $matches[1];
            $relatedOperation = isset($matches[2])
                ? ($matches[2] === 'add'
                    ? Operation::ADD_RELATED
                    : Operation::DELETE_RELATED)
                : null;

            if ($this->hasSaveRelation($type, $operation, $fieldName)) {
                $relation = $this->getSaveRelation($type, $operation, $fieldName);

                if ($relation->getRelatedType()->isList()) {
                    if (!is_array($value)) {
                        throw new ValidationFailedException("Value passed to the many relation {$fieldName} must be an array.");
                    }
                } else {
                    if (!is_array($value) && $value !== null) {
                        throw new ValidationFailedException("Value passed to the singular relation {$fieldName} must be null or an array.");
                    }
                }

                $resolver = $this->createRelationResolver($fieldName, $value);

                if (!$resolver) {
                    throw new InvalidConfigurationException("Relation {$fieldName} on type {$type::type()} does not have a relation resolver.");
                }

                $resolver->relatedOperation($relatedOperation);
                $relationResolvers[$fieldNameWithOperation] = $resolver;
            }
        }

        // ensure #delete resolvers run before #add resolvers,
        // regardless of the key order in the request payload
        uksort($relationResolvers, function ($a, $b) {
            $aIsDelete = str_ends_with($a, '#delete');
            $bIsDelete = str_ends_with($b, '#delete');
            return $bIsDelete <=> $aIsDelete;
        });

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
                    if (!$attribute->hasResolver()) { // let resolvers provide value
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

    protected function createRelationResolver(string $name, mixed $fieldsToSave = null): ?MutationRelationResolver
    {
        $type = $this->type;
        $operation = $this->getOperation();

        if (!$this->hasSaveRelation($type, $operation, $name)) {
            return null;
        }

        $relation = $this->getSaveRelation($type, $operation, $name);
        $resolveCallback = $relation->getResolve();

        if (!$resolveCallback) {
            return null;
        }

        $owner = $this->owner;
        $resolver = invokeResolverCallback(
            $resolveCallback,
            $this->container,
            function (MutationRelationResolver $resolver) use ($relation, $fieldsToSave, $owner) {
                $resolver
                    ->relation($relation)
                    ->fieldsToSave($fieldsToSave);
                if ($owner) {
                    $resolver->addOwner($owner);
                }
            }
        );

        if (!($resolver instanceof MutationRelationResolver)) {
            throw new InvalidConfigurationException("Resolve callback for save relation {$name} on type {$type::type()} must receive a MutationRelationResolver as argument.");
        }

        return $resolver;
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
