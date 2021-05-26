<?php

namespace Afeefa\ApiResources\Field\Fields;

use Afeefa\ApiResources\Field\Relation;

class LinkOneRelation extends Relation
{
    public static string $type = 'Afeefa.LinkOneRelation';

    protected bool $isSingle = true;
}
