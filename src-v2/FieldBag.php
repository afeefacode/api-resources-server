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

    // === Delegation auf das zuletzt erzeugte Field ===

    public function read(Closure|false|null $configure = null): static
    {
        $this->assertLastField('read');
        $this->lastField->read($configure);
        return $this;
    }

    public function write(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = Field::UNSET_DEFAULT,
    ): static {
        $this->assertLastField('write');
        $this->lastField->write($configure, $required, $validate, $mode, $default);
        return $this;
    }

    public function update(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = Field::UNSET_DEFAULT,
    ): static {
        $this->assertLastField('update');
        $this->lastField->update($configure, $required, $validate, $mode, $default);
        return $this;
    }

    public function create(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = Field::UNSET_DEFAULT,
    ): static {
        $this->assertLastField('create');
        $this->lastField->create($configure, $required, $validate, $mode, $default);
        return $this;
    }

    public function options(array $options): static
    {
        $this->assertLastField('options');
        $this->lastField->options($options);
        return $this;
    }

    public function optionsRequest(Closure $callback): static
    {
        $this->assertLastField('optionsRequest');
        $this->lastField->optionsRequest($callback);
        return $this;
    }

    public function setAdditionalSaveFields(Closure $callback): static
    {
        $this->assertLastFieldIsRelation('setAdditionalSaveFields');
        $this->lastField->setAdditionalSaveFields($callback);
        return $this;
    }

    public function skipSaveRelatedIf(Closure $callback): static
    {
        $this->assertLastFieldIsRelation('skipSaveRelatedIf');
        $this->lastField->skipSaveRelatedIf($callback);
        return $this;
    }

    private function assertLastFieldIsRelation(string $method): void
    {
        if (!$this->lastField instanceof Relation) {
            $name = $this->lastField?->getName() ?? '(no field)';
            throw new \Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException(
                "Field {$name}: {$method}() is only valid on relations, not on attributes."
            );
        }
    }

    private function assertLastField(string $method): void
    {
        if ($this->lastField === null) {
            throw new \Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException(
                "FieldBag::{$method}() called before any field was defined — call ->string(), ->hasOne(), etc. first."
            );
        }
    }

    // === Konvertierung in die v1 FieldBags ===

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

    // === v1 Builder-Methoden ueberschreiben, sodass Blueprints angelegt werden ===

    protected function _attribute(string $name, $classOrCallback, ?Closure $callback = null, $validate = null): static
    {
        $blueprint = new Attribute($name, $classOrCallback);

        if ($validate) {
            $blueprint->write(validate: $validate);
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
            $blueprint->write(validate: $validate);
        }

        if ($classOrCallback instanceof Closure) {
            $classOrCallback($blueprint);
        }

        $this->blueprints[$name] = $blueprint;
        $this->lastField = $blueprint;
        return $this;
    }
}
