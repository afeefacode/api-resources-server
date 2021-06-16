<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\RequestedFields;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Model\ModelInterface;
use Afeefa\ApiResources\Type\Type;

class VisibleFields implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    protected RequestedFields $requestedFields;

    public function requestedFields(RequestedFields $requestedFields): VisibleFields
    {
        $this->requestedFields = $requestedFields;
        return $this;
    }

    /**
     * @param ModelInterface[] $models
     */
    public function makeVisible(iterable $models): void
    {
        $this->setVisibleFields($models);
    }

    /**
     * @param ModelInterface[] $models
     */
    protected function setVisibleFields(iterable $models, RequestedFields $fields = null): void
    {
        $fields = $fields ?: $this->requestedFields;

        foreach ($models as $model) {
            $visibleFields = $this->getVisibleFields($model, $fields);
            $model->apiResourcesSetVisibleFields($visibleFields);

            foreach ($fields->getRelations() as $fieldName => $relation) {
                if ($relation->isSingle()) {
                    if ($model->$fieldName) {
                        $this->setVisibleFields([$model->$fieldName], $fields->getNestedField($fieldName));
                    }
                } else {
                    if (is_iterable($model->$fieldName)) {
                        $this->setVisibleFields($model->$fieldName, $fields->getNestedField($fieldName));
                    }
                }
            }
        }
    }

    protected function getVisibleFields(ModelInterface $model, RequestedFields $fields): array
    {
        $typeName = $model->apiResourcesGetType();
        $type = $this->getTypeByName($typeName);

        $visibleFields = ['id', 'type'];

        foreach ($fields->getFieldNamesForType($type) as $fieldName) {
            if ($type->hasField($fieldName)) {
                $visibleFields[] = $fieldName;
            }

            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($type->hasRelation($countRelationName)) {
                    $visibleFields[] = $fieldName;
                }
            }
        }

        return $visibleFields;
    }

    protected function getTypeByName(string $typeName): Type
    {
        return $this->container->call(function (TypeClassMap $typeClassMap) use ($typeName) {
            $TypeClass = $typeClassMap->get($typeName) ?? Type::class;
            return $this->container->get($TypeClass);
        });
    }
}
