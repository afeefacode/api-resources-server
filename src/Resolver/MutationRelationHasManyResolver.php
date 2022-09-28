<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;
use Afeefa\ApiResources\Validator\ValidationFailedException;

class MutationRelationHasManyResolver extends MutationRelationResolver
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

        $owner = $this->owners[0];
        $typeName = $relation->getRelatedType()->getAllTypeNames()[0];
        $data = $this->fieldsToSave;

        if (!is_array($data)) {
            throw new ValidationFailedException('Data passed to an has many relation resolver must be an array of objects.');
        }
        foreach ($data as $single) {
            if (!is_array($single)) {
                throw new ValidationFailedException('Data passed to an has many relation resolver must be an array of objects.');
            }
        }

        if ($relation->hasSkipRelatedIfCallback()) {
            $skipIf = $relation->getSkipRelatedIfCallback();
            $data = array_filter($data, fn ($single) => !$skipIf($single));
        }

        if ($this->ownerOperation === Operation::UPDATE) {
            $existingModels = ($this->getCallback)($owner);
            if (!is_array($existingModels)) {
                throw new InvalidConfigurationException("Get {$mustReturn} an array of ModelInterface objects.");
            }
            foreach ($existingModels as $existingModel) {
                if (!$existingModel instanceof ModelInterface) {
                    throw new InvalidConfigurationException("Get {$mustReturn} an array of ModelInterface objects.");
                }
            }

            $getExistingModelById = fn ($id) => array_values(array_filter($existingModels, fn ($m) => $m->apiResourcesGetId() === $id))[0] ?? null;
            $getSavedDataById = fn ($id) => array_values(array_filter($data, fn ($single) => ($single['id'] ?? null) === $id))[0] ?? null;

            if ($this->relatedOperation !== Operation::ADD_RELATED) { // DEFAULT or DELETE_RELATED, ignore when ADD_RELATED
                foreach ($existingModels as $existingModel) {
                    $id = $existingModel->apiResourcesGetId();
                    // delete if DEFAULT + not included any longer
                    if (!$this->relatedOperation && !$getSavedDataById($id)) {
                        ($this->deleteCallback)($owner, $existingModel);
                    }
                    // or DELETE_RELATED and included in data
                    if ($this->relatedOperation === Operation::DELETE_RELATED && $getSavedDataById($id)) {
                        ($this->deleteCallback)($owner, $existingModel);
                    }
                }
            }

            if ($this->relatedOperation !== Operation::DELETE_RELATED) { // ignore when DELETE_RELATED
                foreach ($data as $single) {
                    $existingModel = $getExistingModelById($single['id'] ?? null);
                    if ($existingModel) {
                        $this->resolveModel($existingModel, $typeName, $single, function (array $saveFields) use ($owner, $existingModel) {
                            $saveFields = $this->addAdditionalSaveFields($saveFields);
                            ($this->updateCallback)($owner, $existingModel, $saveFields);
                            return $existingModel;
                        });
                    } else {
                        $this->resolveModel(null, $typeName, $single, function (array $saveFields) use ($owner, $typeName, $mustReturn) {
                            $saveFields = $this->addAdditionalSaveFields($saveFields);
                            $addedModel = ($this->addCallback)($owner, $typeName, $saveFields);
                            if (!$addedModel instanceof ModelInterface) {
                                throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
                            }
                            return $addedModel;
                        });
                    }
                }
            }
        } else { // create, only add
            if ($this->relatedOperation !== Operation::DELETE_RELATED) { // ignore when DELETE_RELATED
                foreach ($data as $single) {
                    $this->resolveModel(null, $typeName, $single, function (array $saveFields) use ($owner, $typeName, $mustReturn) {
                        $saveFields = $this->addAdditionalSaveFields($saveFields);
                        $addedModel = ($this->addCallback)($owner, $typeName, $saveFields);
                        if (!$addedModel instanceof ModelInterface) {
                            throw new InvalidConfigurationException("Add {$mustReturn} a ModelInterface object.");
                        }
                        return $addedModel;
                    });
                }
            }
        }
    }
}
