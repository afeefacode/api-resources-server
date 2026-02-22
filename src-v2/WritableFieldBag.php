<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Field\Field;
use Afeefa\ApiResources\Field\FieldBag;

class WritableFieldBag extends FieldBag
{
    public function addField(string $name, Field $field): void
    {
        $this->setInternal($name, $field);
    }
}
