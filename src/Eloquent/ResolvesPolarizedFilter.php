<?php

namespace Afeefa\ApiResources\Eloquent;

use Illuminate\Database\Eloquent\Builder;

// Helper for resolving a PolarizedSelectFilter value (include/exclude per id).
//
// The filter value arrives as a flat list where excluded ids carry an `n-`
// prefix, e.g. ['2', '4', 'n-5']. splitPolarizedValue() turns that into
// include/exclude id lists; applyPolarizedFilter() builds the where clauses.
//
// The exclude clause MUST be wrapped in a nested where(...) — otherwise the
// orWhereNull leaks out as a top-level OR and widens the whole query.
trait ResolvesPolarizedFilter
{
    /**
     * @return array{0: array, 1: array} [$includeIds, $excludeIds]
     */
    protected function splitPolarizedValue($value): array
    {
        $includeIds = [];
        $excludeIds = [];

        foreach ((array) $value as $entry) {
            $entry = (string) $entry;
            // Split only at the first `n-` so ids like `n-none` survive.
            if (str_starts_with($entry, 'n-')) {
                $excludeIds[] = substr($entry, 2);
            } else {
                $includeIds[] = $entry;
            }
        }

        return [$includeIds, $excludeIds];
    }

    protected function applyPolarizedFilter(Builder $query, string $column, $value): void
    {
        [$includeIds, $excludeIds] = $this->splitPolarizedValue($value);

        if (!empty($includeIds)) {
            $query->whereIn($column, $includeIds);
        }

        if (!empty($excludeIds)) {
            // Nested where: keep the orWhereNull scoped, don't leak it to top level.
            $query->where(function (Builder $query) use ($column, $excludeIds) {
                $query->whereNotIn($column, $excludeIds)
                    ->orWhereNull($column);
            });
        }
    }
}
