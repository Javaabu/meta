<?php

namespace Javaabu\Meta\Types;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\CheckboxList;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\Select;
use JsonSerializable;

/**
 * Arrays, not indexed. Multiple selects and checkbox lists. whereMeta('tags', 'news')
 * matches lists that contain the value.
 */
class JsonType extends MetaType
{
    public function name(): string
    {
        return 'json';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::Json;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (! is_array($value)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function unserialize(mixed $value): mixed
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    public function field(string $key): Field
    {
        return Select::make($key)->multiple();
    }

    public function matches(Field $field): bool
    {
        if ($field instanceof Select) {
            return $field->isMultiple();
        }

        return $field instanceof CheckboxList;
    }

    public function accepts(mixed $value): bool
    {
        return is_array($value) || $value instanceof Arrayable;
    }

    /**
     * = and != check whether the list contains the value.
     */
    public function where(Builder $query, string $operator, mixed $value, string $key = ''): void
    {
        if (! in_array($operator, ['=', '!=', '<>'], true)) {
            throw MetaException::unsupportedOperator($this->name(), $operator);
        }

        $query->whereJsonContains($this->column()->value, $value, 'and', $operator !== '=');
    }

    /**
     * Lists that contain any of the values (or none of them).
     *
     * @param  array<int, mixed>  $values
     */
    public function whereIn(Builder $query, array $values, bool $not = false, string $key = ''): void
    {
        $nested = function (Builder $query) use ($values) {
            foreach ($values as $value) {
                $query->orWhereJsonContains($this->column()->value, $value);
            }
        };

        $not
            ? $query->whereNot($nested)
            : $query->where($nested);
    }
}
