<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationLinkResolverTrait;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationOneResolverTrait;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;

class MutationRelationLinkOneResolver extends MutationRelationResolver
{
    use MutationRelationOneResolverTrait;
    use MutationRelationLinkResolverTrait;

    public function resolve(): void
    {
        $relation = $this->getRelation();
        $relationName = $this->getRelation()->getName();

        if (!$this->saveRelatedToOwnerCallback) {
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
        }

        $id = $this->fieldsToSave['id'] ?? null;
        $typeName = $relation->getRelatedType()->getAllTypeNames()[0];

        // A.b_id

        if ($this->saveRelatedToOwnerCallback) {
            $this->resolvedId = $id;
            $this->resolvedType = $id ? $typeName : null;
            return;
        }

        // B.a_id or C.a_id,b_id

        $owner = $this->owners[0] ?? null;

        if ($this->operation === Operation::UPDATE) {
            /** @var ModelInterface */
            $existingModel = ($this->getCallback)($owner);

            // unlink if existing is not longer valid
            if ($existingModel
                && (
                    $existingModel->apiResourcesGetId() !== $id
                    || $existingModel->apiResourcesGetType() !== $typeName
                )
            ) {
                ($this->unlinkCallback)($owner, $existingModel);
            }

            // link
            if ($id && (!$existingModel || $existingModel->apiResourcesGetId() !== $id)) {
                ($this->linkCallback)($owner, $id, $typeName);
            }
        } else { // create, only link
            if ($id) {
                ($this->linkCallback)($owner, $id, $typeName);
            }
        }
    }
}
