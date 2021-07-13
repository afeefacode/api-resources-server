<?php

namespace Afeefa\ApiResources\DB;

use Closure;

class MutationResolver extends ActionResolver
{
    protected Closure $saveCallback;

    public function save(Closure $callback): MutationResolver
    {
        $this->saveCallback = $callback;
        return $this;
    }

    public function resolve(): array
    {
        $requestedFields = $this->request->getFields();

        $resolveContext = $this
            ->resolveContext()
            ->requestedFields($requestedFields);

        $saveCallback = $this->saveCallback;
        $result = $saveCallback($resolveContext);

        return [
            'data' => $result,
            'input' => json_decode(file_get_contents('php://input'), true),
            'request' => $this->request
        ];
    }
}
