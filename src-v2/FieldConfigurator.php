<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Field as V1Field;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\V2\Field as V2Field;
use Closure;

/**
 * Api-level Konfiguration eines einzelnen Type-Feldes.
 *
 * Spiegelt die schema-interne Field-Surface bewusst nur teilweise: read()/write()/
 * update()/create() mit `required`, `validate`, `default` als kwargs. Strukturelle
 * Eigenschaften des Schemas (mode, restrictTo, resolve) gehoeren in den Type selbst
 * und sind hier nicht aenderbar.
 *
 * Wird nach dem Bauen aller Types in Api::toSchemaJson() angewendet — die Klasse
 * sammelt die Aenderungen und schreibt sie spaeter auf die fertigen v1-Field-
 * Instanzen je Operation.
 */
class FieldConfigurator
{
    private bool $excludeRead = false;

    private bool $excludeUpdate = false;

    private bool $excludeCreate = false;

    // Operationen, die explizit per (false) ausgeschlossen wurden. Jede spaetere
    // Aussage zu derselben Operation ist Widerspruch und wirft zur Build-Zeit
    // (analog Field, siehe Review Runde 1 Punkt 1).
    private array $explicitlyExcluded = [];

    private array $perOpRequired = [];

    private array $perOpValidate = [];

    private array $perOpDefault = [];

    public function read(mixed $configure = null): static
    {
        if ($configure instanceof \Closure) {
            throw new InvalidConfigurationException(
                'FieldConfigurator::read() does not accept a Closure — resolve and restrictTo are structural and must be configured in the Type itself.'
            );
        }
        if ($configure === null) {
            throw new InvalidConfigurationException(
                'FieldConfigurator::read() requires an explicit argument — use read(false) to exclude the field from READ.'
            );
        }
        $this->excludeRead = true;
        $this->explicitlyExcluded[Operation::READ->value] = true;
        return $this;
    }

    public function write(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        mixed $default = V2Field::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->excludeUpdate = true;
            $this->excludeCreate = true;
            $this->explicitlyExcluded[Operation::UPDATE->value] = true;
            $this->explicitlyExcluded[Operation::CREATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::UPDATE, Operation::CREATE], 'write');
        $this->applyKwargs([Operation::UPDATE, Operation::CREATE], $required, $validate, $default);
        if ($configure instanceof Closure) {
            $configure(new FieldConfiguratorWriteContext($this));
        }
        return $this;
    }

    public function update(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        mixed $default = V2Field::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->excludeUpdate = true;
            $this->explicitlyExcluded[Operation::UPDATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::UPDATE], 'update');
        $this->applyKwargs([Operation::UPDATE], $required, $validate, $default);
        if ($configure instanceof Closure) {
            $configure(new FieldConfiguratorOpContext($this, [Operation::UPDATE]));
        }
        return $this;
    }

    public function create(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        mixed $default = V2Field::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->excludeCreate = true;
            $this->explicitlyExcluded[Operation::CREATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::CREATE], 'create');
        $this->applyKwargs([Operation::CREATE], $required, $validate, $default);
        if ($configure instanceof Closure) {
            $configure(new FieldConfiguratorOpContext($this, [Operation::CREATE]));
        }
        return $this;
    }

    /**
     * @param Operation[] $ops
     */
    private function assertNotExcluded(array $ops, string $method): void
    {
        foreach ($ops as $op) {
            if ($this->explicitlyExcluded[$op->value] ?? false) {
                throw new InvalidConfigurationException(
                    "FieldConfigurator: cannot call {$method}() after {$op->value} was explicitly excluded via (false)."
                );
            }
        }
    }

    public function applyTo(Type $type, string $name, bool $strict = false): void
    {
        // Exclusions
        $this->applyExclude($type, $name, Operation::READ, $this->excludeRead, $strict);
        $this->applyExclude($type, $name, Operation::UPDATE, $this->excludeUpdate, $strict);
        $this->applyExclude($type, $name, Operation::CREATE, $this->excludeCreate, $strict);

        // Per-op modifiers
        $this->applyToBag(Operation::READ, $type->getFields(), $name, $strict);
        $this->applyToBag(Operation::UPDATE, $type->getUpdateFields(), $name, $strict);
        $this->applyToBag(Operation::CREATE, $type->getCreateFields(), $name, $strict);
    }

    private function applyExclude(Type $type, string $name, Operation $op, bool $excludeFlag, bool $strict): void
    {
        if (!$excludeFlag) {
            return;
        }
        $bag = $this->bagFor($type, $op);
        if (!$bag->has($name)) {
            if ($strict) {
                throw new InvalidConfigurationException(
                    "FieldConfigurator: cannot exclude '{$name}' from {$op->value} — not present in that bag."
                );
            }
            return;
        }
        $bag->remove($name);
    }

    private function bagFor(Type $type, Operation $op)
    {
        return match ($op) {
            Operation::READ => $type->getFields(),
            Operation::UPDATE => $type->getUpdateFields(),
            Operation::CREATE => $type->getCreateFields(),
        };
    }

    // === Context-Bridge ===

    public function setRequiredOn(Operation $op, bool $required): void
    {
        $this->perOpRequired[$op->value] = $required;
    }

    public function setValidateOn(Operation $op, callable $validate): void
    {
        $this->perOpValidate[$op->value] = $validate;
    }

    public function setDefaultOn(Operation $op, mixed $default): void
    {
        $this->perOpDefault[$op->value] = $default;
    }

    /** @param Operation[] $ops */
    protected function applyKwargs(
        array $ops,
        ?bool $required,
        ?callable $validate,
        mixed $default,
    ): void {
        foreach ($ops as $op) {
            if ($required !== null) {
                $this->setRequiredOn($op, $required);
            }
            if ($validate !== null) {
                $this->setValidateOn($op, $validate);
            }
            if ($default !== V2Field::UNSET_DEFAULT) {
                $this->setDefaultOn($op, $default);
            }
        }
    }

    private function applyToBag(Operation $op, $bag, string $name, bool $strict): void
    {
        $hasMod = isset($this->perOpRequired[$op->value])
            || isset($this->perOpValidate[$op->value])
            || array_key_exists($op->value, $this->perOpDefault);

        if (!$hasMod) {
            return;
        }

        if (!$bag->has($name)) {
            if ($strict) {
                throw new InvalidConfigurationException(
                    "FieldConfigurator: cannot configure '{$name}' for {$op->value} — not present in that bag."
                );
            }
            return;
        }

        /** @var V1Field $field */
        $field = $bag->get($name);

        if (isset($this->perOpRequired[$op->value])) {
            $field->required($this->perOpRequired[$op->value]);
        }
        if (isset($this->perOpValidate[$op->value])) {
            $field->validate($this->perOpValidate[$op->value]);
        }
        if (array_key_exists($op->value, $this->perOpDefault)) {
            $field->default($this->perOpDefault[$op->value]);
        }
    }
}

/**
 * @internal
 */
class FieldConfiguratorOpContext
{
    /** @param Operation[] $operations */
    public function __construct(
        protected FieldConfigurator $configurator,
        protected array $operations,
    ) {
    }

    public function required(bool $required = true): static
    {
        foreach ($this->operations as $op) {
            $this->configurator->setRequiredOn($op, $required);
        }
        return $this;
    }

    public function validate(callable $validate): static
    {
        foreach ($this->operations as $op) {
            $this->configurator->setValidateOn($op, $validate);
        }
        return $this;
    }

    public function default(mixed $default = V2Field::UNSET_DEFAULT): static
    {
        foreach ($this->operations as $op) {
            $this->configurator->setDefaultOn($op, $default);
        }
        return $this;
    }
}

/**
 * @internal
 */
class FieldConfiguratorWriteContext extends FieldConfiguratorOpContext
{
    public function __construct(FieldConfigurator $configurator)
    {
        parent::__construct($configurator, [Operation::UPDATE, Operation::CREATE]);
    }

    public function update(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        mixed $default = V2Field::UNSET_DEFAULT,
    ): static {
        $this->configurator->update($configure, $required, $validate, $default);
        return $this;
    }

    public function create(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        mixed $default = V2Field::UNSET_DEFAULT,
    ): static {
        $this->configurator->create($configure, $required, $validate, $default);
        return $this;
    }
}
