<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;

class ContainsSearch
{
    /**
     * Narrow the query to rows where any of the columns contains the term,
     * ignoring case. LIKE is case-sensitive on PostgreSQL, so both sides go
     * through lower(); %, _ and the escape character are escaped so user input
     * is always matched literally. Portable across sqlite, MySQL and PostgreSQL.
     *
     * @param  EloquentBuilder<*>|Builder|Relation<*, *, *>  $query
     * @param  list<string>  $columns
     */
    public static function apply(EloquentBuilder|Builder|Relation $query, array $columns, string $term): void
    {
        $pattern = '%'.mb_strtolower(str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term)).'%';

        $query->where(function ($inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw('lower('.$inner->getGrammar()->wrap($column).") like ? escape '!'", [$pattern]);
            }
        });
    }
}
