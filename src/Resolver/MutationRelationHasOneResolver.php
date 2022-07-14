<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;

class MutationRelationHasOneResolver extends MutationRelationResolver
{
    public function resolve(): void
    {
        $relation = $this->getRelation();
        $relationName = $this->getRelation()->getName();

        $needsToImplement = "Resolver for relation {$relationName} needs to implement";
        $mustReturn = "callback of resolver for relation {$relationName} must return";

        if (!$this->getCallback) {
            throw new MissingCallbackException("{$needsToImplement} a get() method.");
        }

        if (!$this->addCallback) {
            throw new MissingCallbackException("{$needsToImplement} an add() method.");
        }

        if (!$this->updateCallback) {
            throw new MissingCallbackException("{$needsToImplement} an update() method.");
        }

        if (!$this->deleteCallback) {
            throw new MissingCallbackException("{$needsToImplement} a delete() method.");
        }

        $typeName = $relation->getRelatedType()->getAllTypeNames()[0];
        $owner = $this->owners[0] ?? null;

        if ($relation->hasSkipRelatedIfCallback()) {
            $skipIf = $relation->getSkipRelatedIfCallback();
            if ($skipIf($this->fieldsToSave)) {
                $this->fieldsToSave = null;
            }
        }

        // A.b_id

        if ($this->saveRelatedToOwnerCallback) {
            if (!$this->addBeforeOwnerCallback) {
                throw new MissingCallbackException("{$needsToImplement} an addBeforeOwner() method.");
            }

            $related = null;

            if ($this->ownerOperation === Operation::UPDATE) { // update owner -> handle related
                $related = $this->handleSaveRelated($owner, $typeName, $mustReturn, $this->fieldsToSave);
            } else { // create owner -> create related
                if (is_array($this->fieldsToSave)) { // add related only if data present
                    $related = $this->resolveModel(null, Operation::CREATE, $typeName, $this->fieldsToSave, function ($saveFields) use ($typeName, $mustReturn) {
                        $saveFields = $this->addAdditionalSaveFields($saveFields);
                        $addedModel = ($this->addBeforeOwnerCallback)($typeName, $saveFields);
                        if (!$addedModel instanceof ModelInterface) {
                            throw new InvalidConfigurationException("AddBeforeOwner {$mustReturn} a ModelInterface object.");
                        }
                        return $addedModel;
                    });
                }
            }

            $this->resolvedId = $related ? $related->apiResourcesGetId() : null;
            $this->resolvedType = $related ? $related->apiResourcesGetType() : null;
            return;
        }

        // B.a_id or C.a_id,b_id

        $this->handleSaveRelated($owner, $typeName, $mustReturn, $this->fieldsToSave);
    }

    protected function handleSaveRelated(ModelInterface $owner, string $typeName, string $mustReturn, ?array $fieldsToSave): ?ModelInterface
    {
        if ($this->ownerOperation === Operation::UPDATE) {
            /** @var ModelInterface */
            $existingModel = ($this->getCallback)($owner);
            if ($existingModel !== null && !$existingModel instanceof ModelInterface) {
                throw new InvalidConfigurationException("Get {$mustReturn} a ModelInterface object or null.");
            }

            if ($existingModel) {
                if ($fieldsToSave === null) { // delete related
                    ($this->deleteCallback)($owner, $existingModel);
                    return null;
                }
                // update related
                return $this->resolveModel($owner, Operation::UPDATE, $typeName, $fieldsToSave, function ($saveFields) use ($owner, $existingModel) {
                    $saveFields = $this->addAdditionalSaveFields($saveFields);
                    ($this->updateCallback)($owner, $existingModel, $saveFields);
                    return $existingModel;
                });
            }

            if (is_array($fieldsToSave)) {
                // add related
                return $this->resolveModel($owner, Operation::CREATE, $typeName, $fieldsToSave, function ($saveFields) use ($owner, $typeName, $mustReturn) {
                    $saveFields = $this->addAdditionalSaveFields($saveFields);
                    $addedModel = ($this->addCallback)($owner, $typeName, $saveFields);
                    if (!$addedModel instanceof ModelInterface) {
                        throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
                    }
                    return $addedModel;
                });
            }
        } else {
            if (is_array($fieldsToSave)) {
                // add related
                return $this->resolveModel($owner, Operation::CREATE, $typeName, $fieldsToSave, function ($saveFields) use ($owner, $typeName, $mustReturn) {
                    $saveFields = $this->addAdditionalSaveFields($saveFields);
                    $addedModel = ($this->addCallback)($owner, $typeName, $saveFields);
                    if (!$addedModel instanceof ModelInterface) {
                        throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
                    }
                    return $addedModel;
                });
            }
        }

        return null;
    }
}
