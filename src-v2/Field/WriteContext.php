<?php

namespace Afeefa\ApiResources\V2\Field;

use Afeefa\ApiResources\V2\Field;
use Afeefa\ApiResources\V2\Operation;
use Closure;

class WriteContext extends OpContext
{
    public function __construct(Field $field)
    {
        parent::__construct($field, [Operation::UPDATE, Operation::CREATE]);
    }

    public function update(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = Field::UNSET_DEFAULT,
    ): static {
        $this->field->update($configure, $required, $validate, $mode, $default);
        return $this;
    }

    public function create(
        Closure|false|null $configure = null,
        ?bool $required = null,
        ?callable $validate = null,
        ?array $mode = null,
        mixed $default = Field::UNSET_DEFAULT,
    ): static {
        $this->field->create($configure, $required, $validate, $mode, $default);
        return $this;
    }
}
