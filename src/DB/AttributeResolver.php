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
     * Closure or array
     */
    protected $ownerIdFields;

    /**
     * @var ModelInterface[]
     */
    protected array $owners = [];

    protected ?string $selectField = null;

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

    public function select(string $fieldName): AttributeResolver
    {
        $this->ownerIdFields([$fieldName]);
        return $this;
    }

    public function ownerIdFields($ownerIdFields): AttributeResolver
    {
        $this->ownerIdFields = $ownerIdFields;
        return $this;
    }

    public function getOwnerIdFields(): array
    {
        if ($this->ownerIdFields instanceof Closure) {
            return ($this->ownerIdFields)() ?? [$this->attribute->getName()];
        }

        return $this->ownerIdFields ?? [$this->attribute->getName()];
    }

    public function addOwner(ModelInterface $owner): AttributeResolver
    {
        $this->owners[] = $owner;
        return $this;
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

    public function resolve()
    {
        // query db

        if (!$this->loadCallback) {
            if ($this->ownerIdFields) {
                return; // only select fields are set up
            }
            throw new MissingCallbackException('attribute resolve callback needs to implement a load() method.');
        }
        $objects = ($this->loadCallback)($this->owners);

        if (!is_iterable($objects) || !is_countable($objects)) {
            throw new InvalidConfigurationException('load() method of an attribute resolver must return a collection.');
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
