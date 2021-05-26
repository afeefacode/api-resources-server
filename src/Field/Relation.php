<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Api\RequestParams;
use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\Type\Type;
use Closure;

/**
 * @method Relation name(string $name)
 * @method Relation validate(Closure $callback)
 * @method Relation validator(Validator $validator)
 * @method Relation required(bool $required = true)
 * @method Relation allowed()
 * @method Relation resolve(string|callable|Closure $classOrCallback)
 */
class Relation extends Field
{
    protected string $RelatedTypeClass;

    protected RequestParams $params;

    protected bool $isSingle = false;

    public function created(): void
    {
        parent::created();

        $this->params = new RequestParams();
    }

    public function isSingle(): bool
    {
        return $this->isSingle;
    }

    public function params(): RequestParams
    {
        return $this->params;
    }

    public function relatedTypeClass(string $RelatedTypeClass): Relation
    {
        $this->RelatedTypeClass = $RelatedTypeClass;

        return $this;
    }

    public function getRelatedTypeClass(): string
    {
        return $this->RelatedTypeClass;
    }

    public function getRelatedTypeInstance(): Type
    {
        return $this->container->get($this->RelatedTypeClass);
    }

    public function clone(): Relation
    {
        /** @var Relation */
        $relation = parent::clone();
        $relation->relatedTypeClass($this->RelatedTypeClass);
        return $relation;
    }

    public function getSchemaJson(TypeRegistry $typeRegistry): array
    {
        $json = parent::getSchemaJson($typeRegistry);

        $typeRegistry->registerType($this->RelatedTypeClass);

        $json['related_type'] = $this->RelatedTypeClass::$type;

        return $json;
    }
}
