<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Field\BaseFieldResolver;
use Afeefa\ApiResources\Resolver\Field\RelationResolverTrait;
use Closure;

class MutationRelationResolver extends BaseFieldResolver
{
    use MutationResolverTrait;
    use RelationResolverTrait;

    public const NOT_RESOLVED = 'not_found';

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

    protected ?Closure $existsCallback = null;

    protected ?string $ownerOperation = null;

    protected ?string $relatedOperation; // null, add_related, delete_related

    protected ?string $resolvedId = self::NOT_RESOLVED;

    protected ?string $resolvedType = null;

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
        if ($this->resolvedId === self::NOT_RESOLVED) { // nothing resolved, related does not exist
            return [];
        }
        return ($this->saveRelatedToOwnerCallback)($this->resolvedId, $this->resolvedType) ?? [];
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
        return ($this->saveOwnerToRelatedCallback)($id, $typeName) ?? [];
    }

    public function ownerOperation(string $ownerOperation): self
    {
        $this->ownerOperation = $ownerOperation;
        return $this;
    }

    public function relatedOperation(?string $operation): self
    {
        $this->relatedOperation = $operation;
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

    public function exists(Closure $callback): self
    {
        $this->existsCallback = $callback;
        return $this;
    }

    public function resolve(): void
    {
    }

    protected function addAdditionalSaveFields(array $saveFields): array
    {
        $relation = $this->getRelation();
        // add additional relation specific save fields
        if ($relation->hasAdditionalSaveFieldsCallback()) {
            $saveFields = array_merge($saveFields, $relation->getAdditionalSaveFieldsCallback()($saveFields));
        }
        return $saveFields;
    }

    public function propagateRelationHooks(?array $ownerFieldsToSave, ?Closure $beforeAdd, ?Closure $beforeUpdate, ?Closure $beforeDelete): void
    {
        $this->ownerFieldsToSave = $ownerFieldsToSave;
        $this->beforeAddRelationCallback = $beforeAdd;
        $this->beforeUpdateRelationCallback = $beforeUpdate;
        $this->beforeDeleteRelationCallback = $beforeDelete;
    }

    protected function callBeforeAddRelation(string $relationName, string $typeName, array $saveFields): array
    {
        if ($this->beforeAddRelationCallback) {
            $saveFields = ($this->beforeAddRelationCallback)($this->ownerFieldsToSave, $relationName, $typeName, $saveFields);
        }
        return $saveFields;
    }

    protected function callBeforeUpdateRelation(string $relationName, ModelInterface $existingModel, array $saveFields): array
    {
        if ($this->beforeUpdateRelationCallback) {
            $saveFields = ($this->beforeUpdateRelationCallback)($this->ownerFieldsToSave, $relationName, $existingModel, $saveFields);
        }
        return $saveFields;
    }

    protected function callBeforeDeleteRelation(string $relationName, ModelInterface $existingModel): void
    {
        if ($this->beforeDeleteRelationCallback) {
            ($this->beforeDeleteRelationCallback)($this->ownerFieldsToSave, $relationName, $existingModel);
        }
    }
}
