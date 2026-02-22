<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Type\Type as V1Type;

class Type extends V1Type
{
    use DefinesFields;

    public function created(): void
    {
        $this->setupV2Fields();
    }
}
