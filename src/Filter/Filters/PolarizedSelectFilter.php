<?php

namespace Afeefa\ApiResources\Filter\Filters;

// Select filter that supports per-entry polarity (include/exclude).
//
// The compact query form `2,4,n-5,n-6` (n- = exclude) is produced and parsed on
// the client (see PolarizedSelectFilter.ts). On the server the filter value
// arrives as the parsed list; use the ResolvesPolarizedFilter trait to split it
// into include/exclude ids and build the where clauses.
class PolarizedSelectFilter extends SelectFilter
{
    protected static string $type = 'Afeefa.PolarizedSelectFilter';
}
