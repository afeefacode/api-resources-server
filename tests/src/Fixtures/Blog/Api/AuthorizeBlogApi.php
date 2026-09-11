<?php

namespace Afeefa\ApiResources\Test\Fixtures\Blog\Api;

use Closure;

/**
 * Blog api whose authorization rules are handed in per test case.
 *
 * The rules are registered in configureAuth(), which runs while the api is
 * created - so the callback has to be set before the api is fetched from the
 * container.
 */
class AuthorizeBlogApi extends BlogApi
{
    public static ?Closure $configureAuthCallback = null;

    protected function configureAuth(): void
    {
        if (static::$configureAuthCallback) {
            (static::$configureAuthCallback)($this);
        }
    }
}
