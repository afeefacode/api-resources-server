<?php

namespace Afeefa\ApiResources\V2\Field;

use Afeefa\ApiResources\V2\Field;
use Afeefa\ApiResources\V2\Operation;

class ReadContext
{
    public function __construct(protected Field $field)
    {
    }

    public function resolve($classOrCallback, array $params = []): static
    {
        $this->field->setResolveOn(Operation::READ, $classOrCallback, $params);
        return $this;
    }

    public function restrictTo(?string $restrictTo): static
    {
        $this->field->setRestrictTo($restrictTo);
        return $this;
    }
}
