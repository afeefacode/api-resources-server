<?php

namespace Afeefa\ApiResources\Test\Eloquent;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\ApiResources;
use Afeefa\ApiResources\Test\Fixtures\Blog\Api\AuthorizeBlogApi;
use Closure;

/**
 * Base for the tests around Api::authorize().
 *
 * Every case comes in pairs: one rule that lets the access through, one that
 * blocks it. Tested is that the rule arrives on the path at all, not what a
 * concrete rule filters.
 */
class ApiResourcesAuthorizeTest extends ApiResourcesEloquentTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AuthorizeBlogApi::$configureAuthCallback = null;
    }

    protected function api(?Closure $configureAuth = null): Api
    {
        AuthorizeBlogApi::$configureAuthCallback = $configureAuth;
        return (new ApiResources())->getApi(AuthorizeBlogApi::class);
    }

    protected function request(?Closure $configureAuth, array $input): array
    {
        return $this->api($configureAuth)->requestFromInput($input);
    }
}
