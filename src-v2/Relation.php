<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Field\Field as V1Field;
use Afeefa\ApiResources\Field\Relation as V1Relation;
use Afeefa\ApiResources\Type\Type as V1Type;
use Afeefa\ApiResources\Type\TypeMeta;
use Closure;

class Relation extends Field
{
    protected $TypeClassOrClasses;

    protected bool $isList = false;

    protected ?string $restrictTo = null;

    protected ?Closure $additionalSaveFieldsCallback = null;

    protected ?Closure $skipSaveRelatedIfCallback = null;

    // Per-Operation mode: 'link', 'save', 'link_or_save'
    protected array $perOpMode = [];

    public function __construct(string $name, $TypeClassOrClassesOrMeta)
    {
        // Don't call parent constructor – Relation doesn't have a v1FieldClass
        $this->name = $name;

        // Unpack TypeMeta if present (from hasMany/linkOne/linkMany compatibility)
        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $this->TypeClassOrClasses = $TypeClassOrClassesOrMeta->TypeClassOrClasses;
            $this->isList = $TypeClassOrClassesOrMeta->list;
            // If link was set via TypeMeta (e.g. from linkOne()), store as default mode
            if ($TypeClassOrClassesOrMeta->link) {
                $this->perOpMode[Operation::UPDATE->value] = 'link';
                $this->perOpMode[Operation::CREATE->value] = 'link';
            }
        } else {
            $this->TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }
    }

    public function onMutation(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::UPDATE, Operation::CREATE], $validate, $required);
        if ($mode !== null) {
            $this->perOpMode[Operation::UPDATE->value] = $mode;
            $this->perOpMode[Operation::CREATE->value] = $mode;
        }
        return $this;
    }

    public function onUpdate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::UPDATE], $validate, $required);
        if ($mode !== null) {
            $this->perOpMode[Operation::UPDATE->value] = $mode;
        }
        return $this;
    }

    public function onCreate(?string $mode = null, $validate = null, ?bool $required = null): static
    {
        $this->setPerOperation([Operation::CREATE], $validate, $required);
        if ($mode !== null) {
            $this->perOpMode[Operation::CREATE->value] = $mode;
        }
        return $this;
    }

    public function restrictTo(?string $restrictTo): static
    {
        $this->restrictTo = $restrictTo;
        return $this;
    }

    public function setAdditionalSaveFields(Closure $callback): static
    {
        $this->additionalSaveFieldsCallback = $callback;
        return $this;
    }

    public function skipSaveRelatedIf(Closure $callback): static
    {
        $this->skipSaveRelatedIfCallback = $callback;
        return $this;
    }

    public function toV1Field(Operation $op, $owner, Container $container): V1Field
    {
        $isMutation = $op !== Operation::READ;

        // Build TypeMeta wrapper based on mode and list flag
        $mode = $this->perOpMode[$op->value] ?? null;
        $isLink = ($mode === 'link' || $mode === 'link_or_save');

        $typeArg = $this->TypeClassOrClasses;
        if ($this->isList) {
            if ($isLink) {
                $typeArg = V1Type::list(V1Type::link($typeArg));
            } else {
                $typeArg = V1Type::list($typeArg);
            }
        } else {
            if ($isLink) {
                $typeArg = V1Type::link($typeArg);
            }
        }

        $v1Relation = $container->create(V1Relation::class, function (V1Relation $relation) use ($op, $isMutation, $owner, $typeArg) {
            $relation
                ->name($this->name)
                ->owner($owner)
                ->isMutation($isMutation)
                ->typeClassOrClassesOrMeta($typeArg);

            // Apply validate: per-operation overrides global
            $validate = $this->perOpValidate[$op->value] ?? $this->validate;
            if ($validate) {
                $relation->validate($validate);
            }

            // Apply required: per-operation overrides global
            $required = $this->perOpRequired[$op->value] ?? $this->required;
            if ($required) {
                $relation->required($required);
            }

            // Apply resolve
            if ($this->resolve !== null) {
                $relation->resolve($this->resolve, $this->resolveParams);
            }

            // Apply restrictTo
            if ($this->restrictTo !== null) {
                $relation->restrictTo($this->restrictTo);
            }

            // Apply additionalSaveFields
            if ($this->additionalSaveFieldsCallback) {
                $relation->setAdditionalSaveFields($this->additionalSaveFieldsCallback);
            }

            // Apply skipSaveRelatedIf
            if ($this->skipSaveRelatedIfCallback) {
                $relation->skipSaveRelatedIf($this->skipSaveRelatedIfCallback);
            }

            // Apply optionsRequest
            if ($this->optionsRequestCallback) {
                $relation->optionsRequest($this->optionsRequestCallback);
            }
        });

        return $v1Relation;
    }
}
