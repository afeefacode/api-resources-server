<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Resolver\Field\BaseFieldResolver;
use Afeefa\ApiResources\Resolver\Field\RelationResolverTrait;
use Closure;

/**
 * @method MutationRelationResolver ownerIdFields($ownerIdFields)
 * @method MutationRelationResolver addOwner($owner)
 * @method MutationRelationResolver relation(Relation $relation)
 */
class MutationRelationResolver extends BaseFieldResolver
{
    use MutationResolverTrait;
    use RelationResolverTrait;

    /**
     * array or null
     */
    protected ?array $fieldsToSave;

    protected MutationResolveContext $resolveContext;

    protected ?Closure $saveRelatedToOwnerCallback = null;

    protected ?Closure $saveOwnerToRelatedCallback = null;

    protected ?Closure $getCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $addBeforeOwnerCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $deleteCallback = null;

    protected ?Closure $linkCallback = null;

    protected ?Closure $unlinkCallback = null;

    protected ?string $ownerOperation = null;

    protected ?string $resolvedId = null;

    protected ?string $resolvedType = null;

    /**
     * fieldsToSave can be null
     */
    public function fieldsToSave(?array $fieldsToSave): self
    {
        $this->fieldsToSave = $fieldsToSave;
        return $this;
    }

    public function saveRelatedToOwner(Closure $callback): self
    {
        $this->saveRelatedToOwnerCallback = $callback;
        return $this;
    }

    public function shouldSaveRelatedToOwner(): bool
    {
        return !!$this->saveRelatedToOwnerCallback;
    }

    public function getSaveRelatedToOwnerFields(): array
    {
        return ($this->saveRelatedToOwnerCallback)($this->resolvedId, $this->resolvedType);
    }

    public function saveOwnerToRelated(Closure $callback): self
    {
        $this->saveOwnerToRelatedCallback = $callback;
        return $this;
    }

    public function shouldSaveOwnerToRelated(): bool
    {
        return !!$this->saveOwnerToRelatedCallback;
    }

    public function getSaveOwnerToRelatedFields(?string $id, ?string $typeName): array
    {
        return ($this->saveOwnerToRelatedCallback)($id, $typeName);
    }

    public function ownerOperation(string $ownerOperation): self
    {
        $this->ownerOperation = $ownerOperation;
        return $this;
    }

    public function get(Closure $callback): self
    {
        $this->getCallback = $callback;
        return $this;
    }

    public function update(Closure $callback): self
    {
        $this->updateCallback = $callback;
        return $this;
    }

    public function addBeforeOwner(Closure $callback): self
    {
        $this->addBeforeOwnerCallback = $callback;
        return $this;
    }

    public function add(Closure $callback): self
    {
        $this->addCallback = $callback;
        return $this;
    }

    public function delete(Closure $callback): self
    {
        $this->deleteCallback = $callback;
        return $this;
    }

    public function link(Closure $callback): self
    {
        $this->linkCallback = $callback;
        return $this;
    }

    public function unlink(Closure $callback): self
    {
        $this->unlinkCallback = $callback;
        return $this;
    }

    public function resolve(): void
    {
    }
}
