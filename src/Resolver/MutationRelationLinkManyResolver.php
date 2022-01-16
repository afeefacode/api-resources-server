<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;

class MutationRelationLinkManyResolver extends MutationRelationResolver
{
    public function resolve(): void
    {
        $relation = $this->getRelation();
        $relationName = $this->getRelation()->getName();

        $needsToImplement = "Resolver for relation {$relationName} needs to implement";

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

        if ($this->operation === Operation::UPDATE) {
            $existingModels = ($this->getCallback)($owner);

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
