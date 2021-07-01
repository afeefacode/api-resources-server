<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Field\Attribute;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;

class AttributeResolver extends DataResolver
{
    protected Attribute $attribute;

    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

    protected ?Closure $loadCallback = null;

    protected ?Closure $mapCallback = null;

    public function attribute(Attribute $attribute): AttributeResolver
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function addOwner(ModelInterface $owner): void
    {
        $this->owners[] = $owner;
    }

    /**
     * @return ModelInterface[]
     */
    public function getOwners(): array
    {
        return $this->owners;
    }

    public function load(Closure $callback): AttributeResolver
    {
        $this->loadCallback = $callback;
        return $this;
    }

    public function map(Closure $callback): AttributeResolver
    {
        $this->mapCallback = $callback;
        return $this;
    }

    public function fetch()
    {
        $resolveContext = $this->resolveContext();

        // query db

        $loadCallback = $this->loadCallback;
        if (!$loadCallback) {
            throw new MissingCallbackException('attribute resolve callback needs to implement a load() method.');
        }
        $objects = $loadCallback($this->owners, $resolveContext);

        if (!is_iterable($objects) || !is_countable($objects)) {
            throw new InvalidConfigurationException('load() method of an attribute resolver must return an iterable+countable.');
        }

        // map results to owners

        if (isset($this->mapCallback)) {
            $mapCallback = $this->mapCallback;
            $attributeName = $this->attribute->getName();

            foreach ($this->owners as $owner) {
                $value = $mapCallback($objects, $owner);
                $owner->apiResourcesSetAttribute($attributeName, $value);
            }
        }
    }
}
