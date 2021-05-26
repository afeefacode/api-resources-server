<?php

namespace Afeefa\ApiResources\Field\Fields;

use Afeefa\ApiResources\Field\Relation;

class HasOneRelation extends Relation
{
    public static string $type = 'Afeefa.HasOneRelation';

    protected bool $isSingle = true;
}
