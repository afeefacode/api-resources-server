<?php

namespace Afeefa\ApiResources\Resolver\Field;

use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Base\BaseResolver;

class BaseFieldResolver extends BaseResolver
{
    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

    public function addOwner(ModelInterface $owner): BaseFieldResolver
    {
        $this->owners[] = $owner;
        return $this;
    }

    public function addOwners(array $owner): BaseFieldResolver
    {
        $this->owners = $owner;
        return $this;
    }

    /**
     * @return ModelInterface[]
     */
    public function getOwners(): array
    {
        return $this->owners;
    }
}
