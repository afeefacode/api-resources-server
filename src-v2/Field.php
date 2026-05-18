<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\DI\Container;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Field as V1Field;
use Afeefa\ApiResources\V2\Field\OpContext;
use Afeefa\ApiResources\V2\Field\ReadContext;
use Afeefa\ApiResources\V2\Field\WriteContext;
use Closure;

class Field
{
    /**
     * Sentinel fuer "default kwarg nicht uebergeben". Trennt explizit null ("kein Default")
     * von "Argument wurde weggelassen".
     */
    public const UNSET_DEFAULT = '__field_default_unset__';

    protected string $name;

    /**
     * v1 Field class or factory callback. null only on Relation, which overrides toV1Field.
     *
     * @var string|Closure|null
     */
    protected $v1FieldClass = null;

    // Default-Mitgliedschaft: ein Field ist in allen drei Bags.
    protected bool $inRead = true;

    protected bool $inUpdate = true;

    protected bool $inCreate = true;

    // Operationen, die explizit per (false) ausgeschlossen wurden. Jede spaetere
    // Aussage zu derselben Operation (auch das blosse "->read()" ohne Args) ist
    // Widerspruch und wirft.
    protected array $explicitlyExcluded = [];

    // Per-Operation-Konfiguration, gekeyed mit Operation->value.
    protected array $perOpRequired = [];

    protected array $perOpValidate = [];

    protected array $perOpDefault = [];

    protected array $perOpResolve = [];

    // Feld-global (gilt fuer Read und Save gleichermassen).
    protected array $options = [];

    protected ?Closure $optionsRequestCallback = null;

    public function __construct(string $name, string $v1FieldClass)
    {
        $this->name = $name;
        $this->v1FieldClass = $v1FieldClass;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function read(Closure|false|null $configure = null): static
    {
        if ($configure === false) {
            $this->inRead = false;
            $this->explicitlyExcluded[Operation::READ->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::READ], 'read');
        $this->inRead = true;
        if ($configure instanceof Closure) {
            $configure(new ReadContext($this));
        }
        return $this;
    }

    public function write(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = self::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->inUpdate = false;
            $this->inCreate = false;
            $this->explicitlyExcluded[Operation::UPDATE->value] = true;
            $this->explicitlyExcluded[Operation::CREATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::UPDATE, Operation::CREATE], 'write');
        $this->inUpdate = true;
        $this->inCreate = true;
        $this->applyKwargs([Operation::UPDATE, Operation::CREATE], $required, $validate, $mode, $default);
        if ($configure instanceof Closure) {
            $configure(new WriteContext($this));
        }
        return $this;
    }

    public function update(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = self::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->inUpdate = false;
            $this->explicitlyExcluded[Operation::UPDATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::UPDATE], 'update');
        $this->inUpdate = true;
        $this->applyKwargs([Operation::UPDATE], $required, $validate, $mode, $default);
        if ($configure instanceof Closure) {
            $configure(new OpContext($this, [Operation::UPDATE]));
        }
        return $this;
    }

    public function create(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = self::UNSET_DEFAULT,
    ): static {
        if ($configure === false) {
            $this->inCreate = false;
            $this->explicitlyExcluded[Operation::CREATE->value] = true;
            return $this;
        }
        $this->assertNotExcluded([Operation::CREATE], 'create');
        $this->inCreate = true;
        $this->applyKwargs([Operation::CREATE], $required, $validate, $mode, $default);
        if ($configure instanceof Closure) {
            $configure(new OpContext($this, [Operation::CREATE]));
        }
        return $this;
    }

    /**
     * @param Operation[] $ops
     */
    protected function assertNotExcluded(array $ops, string $method): void
    {
        foreach ($ops as $op) {
            if ($this->explicitlyExcluded[$op->value] ?? false) {
                throw new InvalidConfigurationException(
                    "Field {$this->name}: cannot call {$method}() after {$op->value} was explicitly excluded via (false)."
                );
            }
        }
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
        return match ($op) {
            Operation::READ => $this->inRead,
            Operation::UPDATE => $this->inUpdate,
            Operation::CREATE => $this->inCreate,
        };
    }

    // === Context-Bridge ===
    // @internal — aufgerufen von ReadContext / OpContext / WriteContext.
    // Nicht Teil der Schema-Autor-Surface; assertNotExcluded() ist nur auf dem
    // oeffentlichen read/write/update/create-Pfad aktiv, nicht hier.

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

    public function setResolveOn(Operation $op, $classOrCallback, array $params = []): void
    {
        $this->perOpResolve[$op->value] = ['callback' => $classOrCallback, 'params' => $params];
    }

    /** @param Operation[] $ops */
    public function setModeOn(array $ops, array $mode): void
    {
        throw new InvalidConfigurationException(
            "Field {$this->name}: mode is only valid for relations."
        );
    }

    public function setRestrictTo(?string $restrictTo): void
    {
        throw new InvalidConfigurationException(
            "Field {$this->name}: restrictTo is only valid for relations."
        );
    }

    public function toV1Field(Operation $op, $owner, Container $container): V1Field
    {
        $isMutation = $op !== Operation::READ;

        return $container->create($this->v1FieldClass, function (V1Field $field) use ($op, $isMutation, $owner) {
            $field
                ->name($this->name)
                ->owner($owner)
                ->isMutation($isMutation);

            if (isset($this->perOpValidate[$op->value])) {
                $field->validate($this->perOpValidate[$op->value]);
            }

            if (($this->perOpRequired[$op->value] ?? false) === true) {
                $field->required(true);
            }

            if (array_key_exists($op->value, $this->perOpDefault)) {
                $field->default($this->perOpDefault[$op->value]);
            }

            if (isset($this->perOpResolve[$op->value])) {
                $resolve = $this->perOpResolve[$op->value];
                $field->resolve($resolve['callback'], $resolve['params']);
            }

            if (count($this->options)) {
                $field->options($this->options);
            }

            if ($this->optionsRequestCallback) {
                $field->optionsRequest($this->optionsRequestCallback);
            }
        });
    }

    /** @param Operation[] $ops */
    protected function applyKwargs(
        array $ops,
        ?bool $required,
        ?callable $validate,
        ?array $mode,
        mixed $default,
    ): void {
        foreach ($ops as $op) {
            if ($required !== null) {
                $this->setRequiredOn($op, $required);
            }
            if ($validate !== null) {
                $this->setValidateOn($op, $validate);
            }
            if ($default !== self::UNSET_DEFAULT) {
                $this->setDefaultOn($op, $default);
            }
        }
        if ($mode !== null) {
            $this->setModeOn($ops, $mode);
        }
    }
}
