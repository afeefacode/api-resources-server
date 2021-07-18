<?php

namespace Afeefa\ApiResources\DB;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;

class MutationResolver extends ActionResolver
{
    protected Closure $saveCallback;

    protected Closure $forwardCallback;

    public function save(Closure $callback): MutationResolver
    {
        $this->saveCallback = $callback;
        return $this;
    }

    public function forward(Closure $callback): ActionResolver
    {
        $this->forwardCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        $requestedFields = $this->request->getFields();
        $fieldsToSave = $this->request->getFieldsToSave();

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields)
            ->fieldsToSave($fieldsToSave);

        $saveCallback = $this->saveCallback;
        $model = $saveCallback($resolveContext);

        if (!$model instanceof ModelInterface) {
            throw new InvalidConfigurationException('A mutation resolver needs to return a ModelInterface instance.');
        }

        foreach ($resolveContext->getSaveRelationResolvers() as $saveRelationResolver) {
            $saveRelationResolver
                ->addOwner($model)
                ->resolve();
        }

        if (isset($this->forwardCallback)) {
            $request = $this->getRequest();
            ($this->forwardCallback)($request, $model);
            return $request->dispatch();
        }

        return [
            'data' => $model,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }
}
