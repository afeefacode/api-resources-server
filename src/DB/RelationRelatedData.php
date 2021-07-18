<?php

namespace Afeefa\ApiResources\DB;

class RelationRelatedData
{
    public ?string $id = null;

    public array $updates = [];

    public ResolveContext $resolveContext;

    public bool $saved = true;
}
