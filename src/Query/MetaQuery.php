<?php

namespace Javaabu\Meta\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use InvalidArgumentException;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\MetaFields;
use Javaabu\Meta\Support\Translations;
use Javaabu\Meta\Types\MetaType;

/**
 * Builds the meta query scopes. A condition is `id IN (select metable_id from
 * meta where metable_type = ? and key = ? and value_* …)`, which the
 * (metable_type, key, value_*, metable_id) indexes answer without reading the
 * rows. A sort is a left join on the (metable_type, metable_id, key, locale)
 * unique index. See docs/advanced-usage/storage-and-performance.md.
 */
class MetaQuery
{
    /** @var array<int, string> */
    public const array OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];

    /**
     * Records whose meta value matches. A null value with = matches records
     * without the key; with != or <>, records with it.
     */
    public static function where(Builder $query, string $key, string $operator, mixed $value, string $boolean = 'and'): void
    {
        $operator = strtolower($operator);

        if (! in_array($operator, static::OPERATORS, true)) {
            throw new InvalidArgumentException("Illegal operator [{$operator}] for meta [{$key}].");
        }

        if ($value === null) {
            static::whereHas($query, $key, $boolean, not: $operator === '=');

            return;
        }

        $model = $query->getModel();
        $type = static::type($model, $key, $value);

        $subquery = static::subquery($model, $key);
        $type->where($subquery, $operator, $value, $key);

        $query->whereIn($model->getQualifiedKeyName(), $subquery, $boolean);
    }

    /**
     * Records whose meta value is one of the values (or, with $not, records that
     * have the key with a value that isn't).
     *
     * @param  iterable<int, mixed>  $values
     */
    public static function whereIn(Builder $query, string $key, iterable $values, string $boolean = 'and', bool $not = false): void
    {
        $values = array_values(is_array($values) ? $values : iterator_to_array($values));
        $model = $query->getModel();
        $type = static::type($model, $key, $values[0] ?? null);

        $subquery = static::subquery($model, $key);
        $type->whereIn($subquery, $values, $not, $key);

        $query->whereIn($model->getQualifiedKeyName(), $subquery, $boolean);
    }

    /**
     * Records that have a value for the key (or, with $not, don't).
     */
    public static function whereHas(Builder $query, string $key, string $boolean = 'and', bool $not = false): void
    {
        $model = $query->getModel();

        $query->whereIn($model->getQualifiedKeyName(), static::subquery($model, $key), $boolean, $not);
    }

    /**
     * Sort by a meta value. Records without it come first in ascending order and
     * last in descending order. Translatable values sort in the current locale,
     * falling back to the default one.
     */
    public static function orderBy(Builder $query, string $key, string $direction = 'asc', ?string $type = null): void
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $model = $query->getModel();
        $registry = app(MetaFields::class);
        $field = $registry->queryField($model::class, $key);

        $meta_type = match (true) {
            $type !== null => $registry->getType($type),
            $field !== null => $field->type,
            default => throw MetaException::unknownSortType($key),
        };

        $locales = $field?->translatable && Translations::enabled()
            ? Translations::fallbackChain()
            : [''];

        $table = static::table($model);
        $base = $query->getQuery();
        $alias = 'meta_sort_' . count($base->joins ?? []);
        $columns = [];

        foreach ($locales as $index => $locale) {
            $join_alias = "{$alias}_{$index}";

            $query->leftJoin("{$table} as {$join_alias}", function (JoinClause $join) use ($join_alias, $model, $key, $locale) {
                $join->on("{$join_alias}.metable_id", '=', $model->getQualifiedKeyName())
                    ->where("{$join_alias}.metable_type", '=', $model->getMorphClass())
                    ->where("{$join_alias}.key", '=', $key)
                    ->where("{$join_alias}.locale", '=', $locale);
            });

            $columns[] = "{$join_alias}.{$meta_type->column()->value}";
        }

        if ($base->columns === null) {
            $query->select($model->qualifyColumn('*'));
        }

        if (count($columns) === 1) {
            $query->orderBy($columns[0], $direction);

            return;
        }

        $grammar = $base->getGrammar();
        $wrapped = implode(', ', array_map(fn (string $column) => $grammar->wrap($column), $columns));

        $query->orderByRaw("coalesce({$wrapped}) {$direction}");
    }

    /**
     * select metable_id from meta where metable_type = ? and key = ?
     */
    public static function subquery(Model $model, string $key): QueryBuilder
    {
        $related = $model->meta()->getRelated();

        return $related->newQueryWithoutScopes()
            ->toBase()
            ->select($related->qualifyColumn('metable_id'))
            ->where($related->qualifyColumn('metable_type'), $model->getMorphClass())
            ->where($related->qualifyColumn('key'), $key);
    }

    public static function table(Model $model): string
    {
        return $model->meta()->getRelated()->getTable();
    }

    /**
     * The defined type of the key, or the type of the value for keys that aren't defined.
     */
    public static function type(Model $model, string $key, mixed $value): MetaType
    {
        $registry = app(MetaFields::class);

        return $registry->queryField($model::class, $key)?->type ?? $registry->typeForValue($value);
    }
}
