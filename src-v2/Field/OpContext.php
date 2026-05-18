<?php

namespace Afeefa\ApiResources\V2\Field;

use Afeefa\ApiResources\V2\Field;
use Afeefa\ApiResources\V2\Operation;

class OpContext
{
    /** @param Operation[] $operations */
    public function __construct(
        protected Field $field,
        protected array $operations,
    ) {
    }

    public function resolve($classOrCallback, array $params = []): static
    {
        foreach ($this->operations as $op) {
            $this->field->setResolveOn($op, $classOrCallback, $params);
        }
        return $this;
    }

    public function required(bool $required = true): static
    {
        foreach ($this->operations as $op) {
            $this->field->setRequiredOn($op, $required);
        }
        return $this;
    }

    public function validate(callable $validate): static
    {
        foreach ($this->operations as $op) {
            $this->field->setValidateOn($op, $validate);
        }
        return $this;
    }

    public function default(mixed $default = Field::UNSET_DEFAULT): static
    {
        foreach ($this->operations as $op) {
            $this->field->setDefaultOn($op, $default);
        }
        return $this;
    }

    public function mode(array $mode): static
    {
        $this->field->setModeOn($this->operations, $mode);
        return $this;
    }
}
