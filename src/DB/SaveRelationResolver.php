<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Api\FieldsToSave;
use Afeefa\ApiResources\Exception\Exceptions\MissingCallbackException;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;

/**
 * @method SaveRelationResolver ownerIdFields($ownerIdFields)
 * @method SaveRelationResolver addOwner(ModelInterface $owner)
 */
class SaveRelationResolver extends RelationResolver
{
    /**
     * @var FieldsToSave|FieldsToSave[]
     */
    protected $fieldsToSave;

    protected ?Closure $setCallback = null;

    protected ?Closure $updateCallback = null;

    protected ?Closure $addCallback = null;

    protected ?Closure $deleteCallback = null;

    /**
     * @param FieldsToSave|FieldsToSave[] $fieldsToSave
     */
    public function fieldsToSave($fieldsToSave): SaveRelationResolver
    {
        $this->fieldsToSave = $fieldsToSave;
        return $this;
    }

    public function set(Closure $callback): SaveRelationResolver
    {
        $this->setCallback = $callback;
        return $this;
    }

    public function update(Closure $callback): SaveRelationResolver
    {
        $this->updateCallback = $callback;
        return $this;
    }

    public function add(Closure $callback): SaveRelationResolver
    {
        $this->addCallback = $callback;
        return $this;
    }

    public function delete(Closure $callback): SaveRelationResolver
    {
        $this->deleteCallback = $callback;
        return $this;
    }

    public function resolve(): void
    {
        $fieldsToSave = $this->fieldsToSave;

        $relation = $this->getRelation();
        $callback = null;

        if ($relation->shallUpdateItems()) {
            $callback = $this->updateCallback;
            if (!$callback) {
                throw new MissingCallbackException('save resolve callback needs to implement a update() method.');
            }
        } elseif ($relation->shallAddItems()) {
            $callback = $this->addCallback;
            if (!$callback) {
                throw new MissingCallbackException('save resolve callback needs to implement a add() method.');
            }
        } elseif ($relation->shallDeleteItems()) {
            $callback = $this->deleteCallback;
            if (!$callback) {
                throw new MissingCallbackException('save resolve callback needs to implement a delete() method.');
            }
        } else { // set items
            $callback = $this->setCallback;
            if (!$callback) {
                throw new MissingCallbackException('save resolve callback needs to implement a set() method.');
            }
        }

        if ($relation->isSingle()) {
        } else {
            $relatedObjects = [];

            foreach ($fieldsToSave as $singleFieldsToSave) {
                $relatedObject = new RelationRelatedData();

                $relatedObject->resolveContext = $this
                    ->resolveContext()
                    ->fieldsToSave($singleFieldsToSave);

                if ($relation->shallAddItems()) {
                    $relatedObject->updates = $relatedObject->resolveContext->getSaveFields();
                } elseif ($relation->shallDeleteItems()) {
                    $relatedObject->id = $singleFieldsToSave->getId();
                    $relatedObject->saved = false; // do not resolve sub relations
                } else { // set or update
                    $relatedObject->id = $singleFieldsToSave->getId();
                    $relatedObject->updates = $relatedObject->resolveContext->getSaveFields();
                }

                $relatedObjects[] = $relatedObject;
            }

            $owner = $this->getOwners()[0];

            $callback($owner, $relatedObjects);

            // save relations of related
            // TODO this needs to be tested
            foreach ($relatedObjects as $relatedObject) {
                if ($relatedObject->saved) {
                    foreach ($relatedObject->resolveContext->getSaveRelationResolvers() as $saveRelationResolver) {
                        $saveRelationResolver->resolve();
                    }
                }
            }
        }
    }
}
