<?php

namespace Afeefa\ApiResources\Resolver;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationHasResolverTrait;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationOneResolverTrait;
use Afeefa\ApiResources\Resolver\Mutation\MutationRelationResolver;
use Closure;

class MutationRelationHasOneResolver extends MutationRelationResolver
{
    use MutationRelationOneResolverTrait;
    use MutationRelationHasResolverTrait;

    protected ?Closure $addBeforeOwnerCallback = null;

    public function addBeforeOwner(Closure $callback): self
    {
        $this->addBeforeOwnerCallback = $callback;
        return $this;
    }

    public function resolve(): void
    {
        $relation = $this->getRelation();
        $relationName = $this->getRelation()->getName();

        $needsToImplement = "Resolver for relation {$relationName} needs to implement";

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

        // A.b_id

        if ($this->saveRelatedToOwnerCallback) {
            if (!$this->addBeforeOwnerCallback) {
                throw new MissingCallbackException("{$needsToImplement} an addBeforeOwner() method.");
            }

            $related = null;

            if ($this->operation === Operation::UPDATE) { // update owner -> handle related
                /** @var ModelInterface */
                $related = $this->handleSaveRelated($owner, $typeName);
            } else { // create owner -> create related
                if (is_array($this->fieldsToSave)) {
                    /** @var ModelInterface */
                    $related = ($this->addBeforeOwnerCallback)($typeName, $this->getSaveFields());
                }
            }

            $this->resolvedId = $related ? $related->apiResourcesGetId() : null;
            $this->resolvedType = $related ? $related->apiResourcesGetType() : null;
            return;
        }

        // B.a_id or C.a_id,b_id

        $this->handleSaveRelated($owner, $typeName);
    }

    protected function handleSaveRelated(ModelInterface $owner, string $typeName): ?ModelInterface
    {
        if ($this->operation === Operation::UPDATE) {
            /** @var ModelInterface */
            $existingModel = ($this->getCallback)($owner);

            if ($existingModel) {
                if ($this->fieldsToSave === null) { // delete related
                    ($this->deleteCallback)($owner, $existingModel);
                    return null;
                }
                // update related
                return ($this->updateCallback)($owner, $existingModel, $this->getSaveFields());
            }

            if (is_array($this->fieldsToSave)) {
                // add related
                return ($this->addCallback)($owner, $typeName, $this->getSaveFields());
            }
        } else {
            if (is_array($this->fieldsToSave)) {
                // add related
                return ($this->addCallback)($owner, $typeName, $this->getSaveFields());
            }
        }

        return null;
    }
}
