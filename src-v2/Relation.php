<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Field as V1Field;
use Afeefa\ApiResources\Field\Relation as V1Relation;
use Afeefa\ApiResources\Type\Type as V1Type;
use Afeefa\ApiResources\Type\TypeMeta;
use Closure;

class Relation extends Field
{
    public const MODE_LINK = 'link';

    public const MODE_CREATE = 'create';

    public const MODE_UPDATE = 'update';

    protected const ALLOWED_MODE_VALUES = [
        self::MODE_LINK,
        self::MODE_CREATE,
        self::MODE_UPDATE,
    ];

    protected $TypeClassOrClasses;

    protected bool $isList = false;

    protected ?string $restrictTo = null;

    protected ?Closure $additionalSaveFieldsCallback = null;

    protected ?Closure $skipSaveRelatedIfCallback = null;

    // Per-Operation mode: array<string> z.B. ['link', 'create'].
    protected array $perOpMode = [];

    public function __construct(string $name, $TypeClassOrClassesOrMeta)
    {
        // Bewusst nicht parent::__construct — Relation hat keinen v1FieldClass.
        $this->name = $name;

        if ($TypeClassOrClassesOrMeta instanceof TypeMeta) {
            $this->TypeClassOrClasses = $TypeClassOrClassesOrMeta->TypeClassOrClasses;
            $this->isList = $TypeClassOrClassesOrMeta->list;
            // linkOne()/linkMany() als Factory: link-Mode wird fuer UPDATE+CREATE
            // als Default gesetzt (READ-Relations brauchen kein link).
            if ($TypeClassOrClassesOrMeta->link) {
                $this->perOpMode[Operation::UPDATE->value] = [self::MODE_LINK];
                $this->perOpMode[Operation::CREATE->value] = [self::MODE_LINK];
            }
        } else {
            $this->TypeClassOrClasses = $TypeClassOrClassesOrMeta;
        }
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

    // === Context-Bridge Overrides ===

    /** @param Operation[] $ops */
    public function setModeOn(array $ops, array $mode): void
    {
        $this->validateModeValue($mode);
        foreach ($ops as $op) {
            $this->validateModeForOperation($op, $mode);
            $this->perOpMode[$op->value] = $mode;
        }
    }

    public function setRestrictTo(?string $restrictTo): void
    {
        $this->restrictTo = $restrictTo;
    }

    public function toV1Field(Operation $op, $owner, Container $container): V1Field
    {
        $isMutation = $op !== Operation::READ;

        $mode = $this->perOpMode[$op->value] ?? null;
        $isLink = is_array($mode) && in_array(self::MODE_LINK, $mode, true);

        $typeArg = $this->TypeClassOrClasses;
        if ($this->isList) {
            $typeArg = $isLink ? V1Type::list(V1Type::link($typeArg)) : V1Type::list($typeArg);
        } elseif ($isLink) {
            $typeArg = V1Type::link($typeArg);
        }

        return $container->create(V1Relation::class, function (V1Relation $relation) use ($op, $isMutation, $owner, $typeArg) {
            $relation
                ->name($this->name)
                ->owner($owner)
                ->isMutation($isMutation)
                ->typeClassOrClassesOrMeta($typeArg);

            if (isset($this->perOpValidate[$op->value])) {
                $relation->validate($this->perOpValidate[$op->value]);
            }

            if (($this->perOpRequired[$op->value] ?? false) === true) {
                $relation->required(true);
            }

            if (isset($this->perOpResolve[$op->value])) {
                $resolve = $this->perOpResolve[$op->value];
                $relation->resolve($resolve['callback'], $resolve['params']);
            }

            if ($this->restrictTo !== null) {
                $relation->restrictTo($this->restrictTo);
            }

            if ($this->additionalSaveFieldsCallback) {
                $relation->setAdditionalSaveFields($this->additionalSaveFieldsCallback);
            }

            if ($this->skipSaveRelatedIfCallback) {
                $relation->skipSaveRelatedIf($this->skipSaveRelatedIfCallback);
            }

            if ($this->optionsRequestCallback) {
                $relation->optionsRequest($this->optionsRequestCallback);
            }
        });
    }

    protected function validateModeValue(array $mode): void
    {
        if (count($mode) === 0) {
            throw new InvalidConfigurationException(
                "Relation {$this->name}: mode must contain at least one value."
            );
        }
        foreach ($mode as $value) {
            if (!in_array($value, self::ALLOWED_MODE_VALUES, true)) {
                $allowed = implode(', ', self::ALLOWED_MODE_VALUES);
                throw new InvalidConfigurationException(
                    "Relation {$this->name}: invalid mode value '{$value}', allowed: {$allowed}."
                );
            }
        }
    }

    protected function validateModeForOperation(Operation $op, array $mode): void
    {
        if ($op === Operation::CREATE && in_array(self::MODE_UPDATE, $mode, true)) {
            throw new InvalidConfigurationException(
                "Relation {$this->name}: mode 'update' is not allowed when creating the parent."
            );
        }
    }
}
