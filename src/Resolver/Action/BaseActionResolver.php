<?php

namespace Afeefa\ApiResources\Resolver\Action;

use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Resolver\Base\BaseResolver;

class BaseActionResolver extends BaseResolver
{
    protected ApiRequest $request;

    public function request(ApiRequest $request): BaseActionResolver
    {
        $this->request = $request;
        return $this;
    }

    public function getRequest(): ApiRequest
    {
        return $this->request;
    }

    public function resolve(): array
    {
        return [];
    }
}
