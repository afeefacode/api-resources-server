<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Type\RelatedType;
use Closure;

class Relation extends Field
{
    public const RESTRICT_TO_GET = 'get';
    public const RESTRICT_TO_COUNT = 'count';

    protected static string $type = 'Afeefa.Relation';

    protected RelatedType $relatedType;

    protected ?string $restrictTo = null;

    protected ?Closure $additionalSaveFieldsCallback = null;

    protected ?Closure $skipSaveRelatedIfCallback = null;

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

    public function skipSaveRelatedIf(Closure $callback): self
    {
        $this->skipSaveRelatedIfCallback = $callback;
        return $this;
    }

    public function hasSkipSaveRelatedIfCallback(): bool
    {
        return !!$this->skipSaveRelatedIfCallback;
    }

    public function getSkipSaveRelatedIfCallback(): ?Closure
    {
        return $this->skipSaveRelatedIfCallback;
    }

    public function typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta): Relation
    {
        $this->relatedType = $this->container->create(RelatedType::class)
            ->relationName($this->name)
            ->initFromArgument($TypeClassOrClassesOrMeta);
        return $this;
    }

    /**
     * More meaningful alias to internal function typeClassOrClassesOrMeta
     */
    public function setRelatedType($TypeClassOrClassesOrMeta): Relation
    {
        return $this->typeClassOrClassesOrMeta($TypeClassOrClassesOrMeta);
    }

    public function getRelatedType(): RelatedType
    {
        return $this->relatedType;
    }

    public function clone(): static
    {
        /** @var Relation */
        $relation = parent::clone();
        $relation->relatedType = $this->relatedType;

        if ($this->additionalSaveFieldsCallback) {
            $relation->additionalSaveFieldsCallback = $this->additionalSaveFieldsCallback;
        }

        if ($this->skipSaveRelatedIfCallback) {
            $relation->skipSaveRelatedIfCallback = $this->skipSaveRelatedIfCallback;
        }

        return $relation;
    }

    public function toSchemaJson(): array
    {
        $json = parent::toSchemaJson();

        $json['related_type'] = $this->relatedType->toSchemaJson();

        return $json;
    }
}
