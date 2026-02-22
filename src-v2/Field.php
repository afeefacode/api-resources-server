<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Field\Field as V1Field;
use Closure;

class Field
{
    protected string $name;

    /**
     * @var string|Closure v1 Field class or factory callback
     */
    protected $v1FieldClass;

    protected array $operations = [];

    // Global config
    protected $validate = null;

    protected bool $required = false;

    protected $default = null;

    protected $resolve = null;

    protected array $resolveParams = [];

    protected array $options = [];

    protected ?Closure $optionsRequestCallback = null;

    // Per-Operation overrides (keyed by Operation->value)
    protected array $perOpValidate = [];

    protected array $perOpRequired = [];

    public function __construct(string $name, string $v1FieldClass)
    {
        $this->name = $name;
        $this->v1FieldClass = $v1FieldClass;
    }

    public function on(Operation ...$operations): static
    {
        $this->operations = array_merge($this->operations, $operations);
        return $this;
    }

    public function onMutation(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::UPDATE, Operation::CREATE], $validate, $required);
        return $this;
    }

    public function onUpdate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::UPDATE], $validate, $required);
        return $this;
    }

    public function onCreate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::CREATE], $validate, $required);
        return $this;
    }

    public function validate($callback): static
    {
        $this->validate = $callback;
        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    public function default($default): static
    {
        $this->default = $default;
        return $this;
    }

    public function resolve($classOrCallback, array $params = []): static
    {
        $this->resolve = $classOrCallback;
        $this->resolveParams = $params;
        return $this;
    }

    public function options(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function optionsRequest(Closure $callback): static
    {
        $this->optionsRequestCallback = $callback;
        return $this;
    }

    public function appliesToOperation(Operation $op): bool
    {
        return in_array($op, $this->operations);
    }

    public function toV1Field(Operation $op, $owner, Container $container): V1Field
    {
        $isMutation = $op !== Operation::READ;

        $v1Field = $container->create($this->v1FieldClass, function (V1Field $field) use ($op, $isMutation, $owner) {
            $field
                ->name($this->name)
                ->owner($owner)
                ->isMutation($isMutation);

            // Apply validate: per-operation overrides global
            $validate = $this->perOpValidate[$op->value] ?? $this->validate;
            if ($validate) {
                $field->validate($validate);
            }

            // Apply required: per-operation overrides global
            $required = $this->perOpRequired[$op->value] ?? $this->required;
            if ($required) {
                $field->required($required);
            }

            // Apply default
            if ($this->default !== null) {
                $field->default($this->default);
            }

            // Apply resolve
            if ($this->resolve !== null) {
                $field->resolve($this->resolve, $this->resolveParams);
            }

            // Apply options
            if (count($this->options)) {
                $field->options($this->options);
            }

            // Apply optionsRequest
            if ($this->optionsRequestCallback) {
                $field->optionsRequest($this->optionsRequestCallback);
            }
        });

        return $v1Field;
    }

    protected function setPerOperation(array $operations, $validate, ?bool $required): void
    {
        foreach ($operations as $op) {
            if ($validate !== null) {
                $this->perOpValidate[$op->value] = $validate;
            }
            if ($required !== null) {
                $this->perOpRequired[$op->value] = $required;
            }
        }
    }
}
