<?php

namespace Afeefa\ApiResources\DB;

class SkipRelationResolver
{
    public function skip(RelationResolver $r)
    {
        $r->load(function ($owners) {
            return [];
        });
    }
}
