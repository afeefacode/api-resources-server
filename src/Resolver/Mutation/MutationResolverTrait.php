<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Resolver\Base\BaseResolver;
use Closure;

trait MutationResolverTrait
{
    protected array $ownerSaveFields = [];

    public function ownerSaveFields(array $ownerSaveFields): BaseResolver
    {
        $this->ownerSaveFields = $ownerSaveFields;
        return $this;
    }

    protected function resolveModel(?ModelInterface $owner, string $ownerOperation, string $typeName, array $fieldsToSave, Closure $resolveCallback): ModelInterface
    {
        $resolveContext = $this->createResolveContext($typeName, $ownerOperation, $fieldsToSave);

        // resolve relations before this model (relation can only be hasOne or linkOne)

        $relatedSaveFields = [];

        $relationResolvers = $resolveContext->getRelationResolvers();

        foreach ($relationResolvers as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                $relationResolver->ownerOperation($ownerOperation);
                if ($owner) {
                    $relationResolver->addOwner($owner);
                }
                $relationResolver->resolve();
                $relatedSaveFields = array_merge($relatedSaveFields, $relationResolver->getSaveRelatedToOwnerFields());
            }
        }

        // resolve this model

        $saveFields = array_merge($resolveContext->getSaveFields(), $this->ownerSaveFields, $relatedSaveFields);

        $owner = $resolveCallback($saveFields);

        // resolve relations after this model

        foreach ($relationResolvers as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                continue; // already resolved
            }

            $ownerSaveFields = [];

            // save owner field to related

            if ($relationResolver->shouldSaveOwnerToRelated()) {
                $ownerSaveFields = $relationResolver->getSaveOwnerToRelatedFields(
                    $owner->apiResourcesGetId(),
                    $owner->apiResourcesGetType()
                );
            }

            $relationResolver
                ->ownerOperation($ownerOperation)
                ->addOwner($owner)
                ->ownerSaveFields($ownerSaveFields)
                ->resolve();
        }

        return $owner;
    }

    private function createResolveContext(string $typeName, string $operation, array $fieldsToSave): MutationResolveContext
    {
        return $this->container->create(MutationResolveContext::class)
            ->type($this->getTypeByName($typeName))
            ->operation($operation)
            ->fieldsToSave($fieldsToSave);
    }
}
