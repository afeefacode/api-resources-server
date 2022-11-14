<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Api\Operation;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Validator\ValidationFailedException;
use Closure;

trait MutationResolverTrait
{
    protected array $ownerSaveFields = [];

    public function ownerSaveFields(array $ownerSaveFields): static
    {
        $this->ownerSaveFields = $ownerSaveFields;
        return $this;
    }

    protected function resolveModel(?ModelInterface $existingModel, string $typeName, ?array $fieldsToSave, Closure $resolveCallback): ModelInterface
    {
        $ownerOperation = $existingModel ? Operation::UPDATE : Operation::CREATE;

        $resolveContext = $this->createResolveContext($typeName, $ownerOperation, $fieldsToSave);

        // ensure all required fields are given

        $requiredFieldNames = $resolveContext->getRequiredFieldNames();
        foreach ($requiredFieldNames as $requiredFieldName) {
            if (!isset($fieldsToSave[$requiredFieldName])) {
                throw new ValidationFailedException("Field {$requiredFieldName} ist required but not given.");
            }
        }

        // resolve relations before this model (relation can only be hasOne or linkOne)

        $relatedSaveFields = [];

        $relationResolvers = $resolveContext->getRelationResolvers();

        foreach ($relationResolvers as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                $relationResolver->ownerOperation($ownerOperation);
                if ($existingModel) {
                    $relationResolver->addOwner($existingModel);
                }
                $relationResolver->resolve();
                $relatedSaveFields = array_merge($relatedSaveFields, $relationResolver->getSaveRelatedToOwnerFields());
            }
        }

        // resolve this model

        /**
         * If we have a reflexive recursive relation: owner->related->owner, we will establish
         * the relation by taking the ownerSaveFields in favor of the $relatedSaveFields which
         * in the case of create operation will have the owner_id set to null which would then
         * override the owner_id passed from ownerSaveFields, so the order is, that owner relation
         * fields override related relation fields.
         */
        $saveFields = array_merge($resolveContext->getSaveFields(), $relatedSaveFields, $this->ownerSaveFields);

        $model = $resolveCallback($saveFields);

        // resolve relations after this model

        foreach ($relationResolvers as $relationResolver) {
            if ($relationResolver->shouldSaveRelatedToOwner()) {
                continue; // already resolved
            }

            $ownerSaveFields = [];

            // save owner field to related

            if ($relationResolver->shouldSaveOwnerToRelated()) {
                $ownerSaveFields = $relationResolver->getSaveOwnerToRelatedFields(
                    $model->apiResourcesGetId(),
                    $model->apiResourcesGetType()
                );
            }

            $relationResolver
                ->ownerOperation($ownerOperation)
                ->addOwner($model)
                ->ownerSaveFields($ownerSaveFields)
                ->resolve();
        }

        return $model;
    }

    private function createResolveContext(string $typeName, string $operation, ?array $fieldsToSave): MutationResolveContext
    {
        return $this->container->create(MutationResolveContext::class)
            ->type($this->getTypeByName($typeName))
            ->operation($operation)
            ->fieldsToSave($fieldsToSave);
    }
}
