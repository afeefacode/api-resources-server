<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Type\RelatedType;
use Closure;

/**
 * @method Relation owner($owner)
 * @method Relation name(string $name)
 * @method Relation validate(Closure $callback)
 * @method Relation validator(Validator $validator)
 * @method Relation required(bool $required = true)
 * @method Relation resolve(string|callable|Closure $classOrCallback, array $params = [])
 * @method Relation isMutation(bool $isMutation)
 */
class Relation extends Field
{
    public const RESTRICT_TO_GET = 'get';
    public const RESTRICT_TO_COUNT = 'count';

    protected static string $type = 'Afeefa.Relation';

    protected RelatedType $relatedType;

    protected ?string $restrictTo = null;

    protected ?Closure $additionalSaveFieldsCallback = null;

    public function restrictTo(?string $restrictTo): Relation
    {
        $this->restrictTo = $restrictTo;
        return $this;
    }

    public function isRestrictedTo(string $restrictedTo): bool
    {
        return $this->restrictTo === $restrictedTo;
    }

    public function setAdditionalSaveFields(Closure $callback): self
    {
        $this->additionalSaveFieldsCallback = $callback;
        return $this;
    }

    public function hasAdditionalSaveFieldsCallback(): bool
    {
        return !!$this->additionalSaveFieldsCallback;
    }

    public function getAdditionalSaveFieldsCallback(): ?Closure
    {
        return $this->additionalSaveFieldsCallback;
    }

    public function typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta): Relation
    {
        $this->relatedType = $this->container->create(RelatedType::class)
            ->relationName($this->name)
            ->initFromArgument($TypeClassOrClassesOrMeta);
        return $this;
    }

    public function getRelatedType(): RelatedType
    {
        return $this->relatedType;
    }

    public function clone(): Relation
    {
        /** @var Relation */
        $relation = parent::clone();
        $relation->relatedType = $this->relatedType;
        return $relation;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        $json['related_type'] = $this->relatedType->toSchemaJson();

        return $json;
    }
}
