<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\Container;

/**
 * The rule that was registered for the same type and operation before the one
 * currently running.
 *
 * A rule asks for it by declaring a parameter of this type. It then decides
 * itself whether and when the earlier rule applies - for instance an
 * application that lets one extra role see every article, and hands every
 * other account on to the rule of the library it builds on:
 *
 *     $api->authorize(ArticleType::class)->read(
 *         function (EloquentAuthContext $c, EditorService $editors, PreviousAuthRule $previous) {
 *             if ($editors->isChiefEditor()) {
 *                 return;
 *             }
 *             $previous->call($c);
 *         }
 *     );
 *
 * The application neither needs to know nor to repeat what the earlier rule does.
 */
class PreviousAuthRule
{
    public function __construct(
        protected ?AuthRule $rule,
        protected Container $container
    ) {
    }

    /**
     * Applies the earlier rule to the context. Without an earlier rule this does
     * nothing, just as a slot that was never set restricts nothing.
     */
    public function call(AuthContext $context): void
    {
        $this->rule?->call($context, $this->container);
    }

    public function exists(): bool
    {
        return $this->rule !== null;
    }
}
