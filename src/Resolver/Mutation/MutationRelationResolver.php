<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
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

    protected ?Closure $resolveAfterOwnerCallback = null;

    protected array $ownerSaveFields = [];

    protected ?Closure $getCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $deleteCallback = null;

    protected ?Closure $linkCallback = null;

    protected ?Closure $unlinkCallback = null;

    protected ?string $operation = null;

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

    public function ownerSaveFields(array $ownerSaveFields): self
    {
        $this->ownerSaveFields = $ownerSaveFields;
        return $this;
    }

    public function getSaveFields(): array
    {
        return $this->getResolveContext2()->getSaveFields($this->ownerSaveFields);
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
        $this->resolveAfterOwnerCallback = $callback;
        return $this;
    }

    public function shouldSaveOwnerToRelated(): bool
    {
        return !!$this->resolveAfterOwnerCallback;
    }

    public function getSaveOwnerToRelatedFields(?string $id, ?string $typeName): array
    {
        return ($this->resolveAfterOwnerCallback)($id, $typeName);
    }

    public function operation(string $operation): self
    {
        $this->operation = $operation;
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
        $relation = $this->getRelation();
        $relationName = $this->getRelation()->getName();

        $needsToImplement = "Resolver for relation {$relationName} needs to implement";
        $needsToReturn = "Resolver for relation {$relationName} needs to return";

        /** @var ModelInterface */
        $owner = $this->owners[0] ?? null;
        $related = null;

        if (!$this->getCallback) {
            throw new MissingCallbackException("{$needsToImplement} a get() method.");
        }

        if ($relation->isLink()) {
            if (!$this->linkCallback) {
                throw new MissingCallbackException("{$needsToImplement} a link() method.");
            }

            if (!$this->unlinkCallback) {
                throw new MissingCallbackException("{$needsToImplement} an unlink() method.");
            }
        } else {
            if (!$this->addCallback) {
                throw new MissingCallbackException("{$needsToImplement} an add() method.");
            }

            if (!$this->updateCallback) {
                throw new MissingCallbackException("{$needsToImplement} an update() method.");
            }

            if (!$this->deleteCallback) {
                throw new MissingCallbackException("{$needsToImplement} a delete() method.");
            }
        }

        if ($relation->isSingle()) {
            $data = $this->fieldsToSave;

            $typeName = $relation->getRelatedType()->getAllTypeNames()[0];

            /** @var ModelInterface */
            $existingModel = ($this->getCallback)($owner);  // todo validate existance for link one relation + unlink

            if ($relation->isLink()) { // link one
                if ($data !== null) {
                    if (isset($data['id'])) {
                        if ($existingModel && $existingModel->apiResourcesGetId() !== $data['id']) {
                            $related = ($this->unlinkCallback)($owner, $existingModel);
                        }

                        if (!$existingModel || $existingModel->apiResourcesGetId() !== $data['id']) {
                            $related = ($this->linkCallback)($owner, $data['id'], $typeName);
                        }

                        if (!$related) {
                            $related = $existingModel;
                        }
                    }
                } else {
                    if ($existingModel) {
                        $related = ($this->unlinkCallback)($owner, $existingModel);
                    }
                }
            } else { // has one
                if ($data !== null) {
                    if ($existingModel) {
                        $related = ($this->updateCallback)($owner, $existingModel, $this->getSaveFields());
                    } else {
                        $related = ($this->addCallback)($owner, $typeName, $this->getSaveFields());
                    }
                } else {
                    if ($existingModel) {
                        $related = ($this->deleteCallback)($owner, $existingModel);
                    }
                }
            }

            if ($related) {
                $this->resolvedId = $related->apiResourcesGetId();
                $this->resolvedType = $related->apiResourcesGetType();
            }
        } else { // has or link many
            $data = $this->fieldsToSave;

            $typeName = $relation->getRelatedType()->getAllTypeNames()[0];

            $existingModels = ($this->getCallback)($owner);

            if (!is_array($existingModels)) {
                throw new InvalidConfigurationException("{$needsToReturn} an array from its get() method.");
            }

            $getExistingModelById = fn ($id) => array_values(array_filter($existingModels, fn ($m) => $m->apiResourcesGetId() === $id))[0] ?? null;
            $getSavedDataById = fn ($id) => array_values(array_filter($data, fn ($single) => ($single['id'] ?? null) === $id))[0] ?? null;

            if ($relation->isLink()) { // link many
                foreach ($existingModels as $existingModel) {
                    $id = $existingModel->apiResourcesGetId();
                    if (!$getSavedDataById($id)) {
                        ($this->unlinkCallback)($owner, $existingModel);
                    }
                }

                foreach ($data as $single) {
                    if (isset($single['id'])) {
                        $existingModel = $getExistingModelById($single['id']);
                        if (!$existingModel || $existingModel->apiResourcesGetId() !== $single['id']) {
                            $related = ($this->linkCallback)($owner, $single['id'], $typeName);
                        }
                    }
                }
            } else { // has many
                foreach ($existingModels as $existingModel) {
                    $id = $existingModel->apiResourcesGetId();
                    if (!$getSavedDataById($id)) {
                        ($this->deleteCallback)($owner, $existingModel);
                    }
                }

                foreach ($data as $single) {
                    $resolveContext = $this->container->create(MutationResolveContext::class)
                        ->type($this->getTypeByName($typeName))
                        ->fieldsToSave($single);

                    $existingModel = $getExistingModelById($single['id'] ?? null);
                    if ($existingModel) {
                        $related = ($this->updateCallback)($owner, $existingModel, $resolveContext->getSaveFields());
                    } else {
                        $related = ($this->addCallback)($owner, $typeName, $resolveContext->getSaveFields());
                    }
                }
            }
        }
    }

    protected function getResolveContext2(): MutationResolveContext
    {
        if (!isset($this->resolveContext)) {
            $owner = $this->owners[0] ?? null;
            $typeName = $this->getRelation()->getRelatedType()->getTypeClass()::type();

            $this->resolveContext = $this->container->create(MutationResolveContext::class)
                ->owner($owner)
                ->type($this->getTypeByName($typeName))
                ->fieldsToSave($this->fieldsToSave);
        }
        return $this->resolveContext;
    }
}
