<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Field\FieldBag as V1FieldBag;
use Afeefa\ApiResources\Field\Relation as V1Relation;
use Closure;

class FieldBag extends V1FieldBag
{
    /**
     * @var Field[]
     */
    protected array $blueprints = [];

    protected ?Field $lastField = null;

    // === v2 methods that delegate to lastField ===

    public function on(Operation ...$operations): static
    {
        $this->lastField->on(...$operations);
        return $this;
    }

    public function onMutation(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->lastField->onMutation($mode, $validate, $required);
        return $this;
    }

    public function onUpdate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->lastField->onUpdate($mode, $validate, $required);
        return $this;
    }

    public function onCreate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->lastField->onCreate($mode, $validate, $required);
        return $this;
    }

    // === Delegation methods for field config (chaining from FieldBag) ===

    public function validate($callback): static
    {
        $this->lastField->validate($callback);
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->lastField->required($required);
        return $this;
    }

    public function default($default): static
    {
        $this->lastField->default($default);
        return $this;
    }

    public function resolve($classOrCallback, array $params = []): static
    {
        $this->lastField->resolve($classOrCallback, $params);
        return $this;
    }

    public function options(array $options): static
    {
        $this->lastField->options($options);
        return $this;
    }

    public function optionsRequest(\Closure $callback): static
    {
        $this->lastField->optionsRequest($callback);
        return $this;
    }

    public function restrictTo(?string $restrictTo): static
    {
        if ($this->lastField instanceof Relation) {
            $this->lastField->restrictTo($restrictTo);
        }
        return $this;
    }

    public function setAdditionalSaveFields(\Closure $callback): static
    {
        if ($this->lastField instanceof Relation) {
            $this->lastField->setAdditionalSaveFields($callback);
        }
        return $this;
    }

    public function skipSaveRelatedIf(\Closure $callback): static
    {
        if ($this->lastField instanceof Relation) {
            $this->lastField->skipSaveRelatedIf($callback);
        }
        return $this;
    }

    // === Conversion to v1 FieldBags ===

    public function forOperation(Operation $operation): WritableFieldBag
    {
        $v1Bag = $this->container->create(WritableFieldBag::class)
            ->owner($this->getOwner());

        if ($operation !== Operation::READ) {
            $v1Bag->isMutation();
        }

        foreach ($this->blueprints as $name => $blueprint) {
            if ($blueprint->appliesToOperation($operation)) {
                $v1Field = $blueprint->toV1Field($operation, $this->getOwner(), $this->container);
                $v1Bag->addField($name, $v1Field);
            }
        }

        return $v1Bag;
    }

    // === Override v1 builder methods to create blueprints ===

    protected function _attribute(string $name, $classOrCallback, ?Closure $callback = null, $validate = null): static
    {
        $blueprint = new Attribute($name, $classOrCallback);

        if ($validate) {
            $blueprint->validate($validate);
        }

        if ($callback) {
            $callback($blueprint);
        }

        $this->blueprints[$name] = $blueprint;
        $this->lastField = $blueprint;
        return $this;
    }

    protected function _relation(string $name, $TypeClassOrClassesOrMeta, $classOrCallback = V1Relation::class, $validate = null): static
    {
        $blueprint = new Relation($name, $TypeClassOrClassesOrMeta);

        if ($validate) {
            $blueprint->validate($validate);
        }

        // If a Closure callback was passed (e.g. from hasOne with callback), apply it to blueprint
        if ($classOrCallback instanceof Closure) {
            $classOrCallback($blueprint);
        }

        $this->blueprints[$name] = $blueprint;
        $this->lastField = $blueprint;
        return $this;
    }
}
