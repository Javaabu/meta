<?php

namespace Javaabu\Meta\Types;

use Illuminate\Database\Query\Builder;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TextInput;

/**
 * How a meta value is stored, cast, queried and edited. Extend it to add a type:
 * pick a column, convert values to and from it, and give the stencil field that
 * edits it.
 */
abstract class MetaType
{
    /**
     * The type's name: stored with each value and used in definitions ('views' => 'integer').
     */
    abstract public function name(): string;

    /**
     * The column values are stored in.
     */
    abstract public function column(): MetaColumn;

    /**
     * Turns a value into what's stored in the column. Never receives null.
     */
    public function serialize(mixed $value, string $key = ''): mixed
    {
        return $value;
    }

    /**
     * Turns what's stored back into a value. Never receives null.
     */
    public function unserialize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * The stencil field that edits a key defined only by its type: MetaFields::for(Post::class, ['views' => 'integer']).
     */
    public function field(string $key): Field
    {
        return TextInput::make($key);
    }

    /**
     * Whether values of a stencil field are stored as this type. Used to infer the
     * type of a field without ->metaType(). Types registered later are asked first.
     */
    public function matches(Field $field): bool
    {
        return false;
    }

    /**
     * Whether a PHP value is stored as this type when its key isn't defined.
     * Types registered later are asked first.
     */
    public function accepts(mixed $value): bool
    {
        return false;
    }

    /**
     * Validation rules for values of this type, added to the stencil field's own.
     *
     * @return array<int, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Constrain a query on the meta table to rows whose value matches. The query
     * already has the record type and key.
     */
    public function where(Builder $query, string $operator, mixed $value, string $key = ''): void
    {
        $query->where($this->column()->value, $operator, $this->serialize($value, $key));
    }

    /**
     * Constrain a query on the meta table to rows whose value is (or isn't) one of the values.
     *
     * @param  array<int, mixed>  $values
     */
    public function whereIn(Builder $query, array $values, bool $not = false, string $key = ''): void
    {
        $query->whereIn(
            $this->column()->value,
            array_map(fn ($value) => $this->serialize($value, $key), $values),
            'and',
            $not,
        );
    }
}
