<?php

namespace Afeefa\ApiResources\Resolver\Field;

use Afeefa\ApiResources\Field\Attribute;

trait AttributeResolverTrait
{
    protected Attribute $attribute;

    public function attribute(Attribute $attribute): self
    {
        $this->attribute = $attribute;
        return $this;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getResolveParams(): array
    {
        return $this->attribute->getResolveParams();
    }

    public function getResolveParam(string $name)
    {
        return $this->attribute->getResolveParam($name);
    }
}
