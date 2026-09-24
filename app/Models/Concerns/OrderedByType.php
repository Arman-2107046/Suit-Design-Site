<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Orders an option list the way the admin dragged its types.
 *
 * Every tile in the designer is labelled by its type — "Single-breasted 2
 * buttons", "Peak", "Flap" — so the type is what an admin means when they
 * reorder. It is also the only list that can be dragged: there are six body
 * types but 188 bodies, five lapel categories but 4,324 lapels, because each
 * fabric carries its own set. Dragging the six reorders every fabric at once.
 */
trait OrderedByType
{
    /**
     * @param  array<class-string, string>  $types  type model => foreign key, outermost grouping first
     */
    protected function orderedByType(HasMany $relation, array $types): HasMany
    {
        $table = $relation->getRelated()->getTable();


        foreach ($types as $type => $foreignKey) {

            /* A correlated subquery rather than a join: eager loading rebuilds
               these queries, and a join would collide with its own select. */
            $relation->orderBy(
                $type::select('sort_order')
                    ->whereColumn((new $type)->getTable().'.id', "{$table}.{$foreignKey}")
            );
        }


        /* The option's own order settles ties inside a type; the id settles the rest. */
        return $relation
            ->orderBy("{$table}.sort_order")
            ->orderBy("{$table}.id");
    }
}
