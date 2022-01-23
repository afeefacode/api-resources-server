<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;

class MutationRelationLinkManyResolver extends MutationRelationResolver
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

        if (!$this->linkCallback) {
            throw new MissingCallbackException("{$needsToImplement} a link() method.");
        }

        if (!$this->unlinkCallback) {
            throw new MissingCallbackException("{$needsToImplement} an unlink() method.");
        }

        $owner = $this->owners[0];
        $typeName = $relation->getRelatedType()->getAllTypeNames()[0];
        $data = $this->fieldsToSave;

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

            foreach ($existingModels as $existingModel) {
                $id = $existingModel->apiResourcesGetId();
                if (!$getSavedDataById($id)) {
                    ($this->unlinkCallback)($owner, $existingModel);
                }
            }

            foreach ($data as $single) {
                if (isset($single['id'])) {
                    $existingModel = $getExistingModelById($single['id']);
                    if (!$existingModel) {
                        ($this->linkCallback)($owner, $single['id'], $typeName);
                    }
                }
            }
        } else { // create, only link
            foreach ($data as $single) {
                if (isset($single['id'])) {
                    ($this->linkCallback)($owner, $single['id'], $typeName);
                }
            }
        }
    }
}
