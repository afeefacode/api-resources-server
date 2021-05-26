<?php

namespace Afeefa\ApiResources\Bag;

use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\DI\ContainerAwareTrait;

class BagEntry implements BagEntryInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;
}
