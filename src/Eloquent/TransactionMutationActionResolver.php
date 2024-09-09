<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Resolver\MutationActionResolver;
use Closure;
use Illuminate\Database\Capsule\Manager as DB;

class TransactionMutationActionResolver extends MutationActionResolver
{
    public function __construct()
    {
        $this->transactionCallback = function (Closure $execute) {
            return DB::transaction(function () use ($execute) {
                return $execute();
            });
        };
    }
}
