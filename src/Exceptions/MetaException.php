<?php

namespace Javaabu\Meta\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class MetaException extends RuntimeException
{
    public static function unknownType(string $type): static
    {
        return new static("Unknown meta type [{$type}]. Register it with MetaFields::type() or in config('meta.types').");
    }

    public static function invalidType(string $class): static
    {
        return new static("[{$class}] is not a meta type. Meta types extend Javaabu\\Meta\\Types\\MetaType.");
    }

    public static function notMetable(string $model): static
    {
        return new static("[{$model}] doesn't use the Javaabu\\Meta\\Concerns\\HasMeta trait.");
    }

    public static function valueTooLong(string $key, int $max): static
    {
        return new static("The value of meta [{$key}] is longer than {$max} characters. Define it with a text type (a Textarea) instead.");
    }

    public static function invalidValue(string $key, string $type): static
    {
        return new static("The value of meta [{$key}] can't be stored as [{$type}].");
    }

    public static function ambiguousKey(string $model, string $key): static
    {
        return new static("Meta [{$key}] of [{$model}] is defined with different types in different groups. Give it one type to query it.");
    }

    public static function unsupportedField(string $key, string $field): static
    {
        return new static("Meta [{$key}] can't use a [{$field}] that saves to a relationship. Meta fields save to the meta table.");
    }

    public static function notFillable(Model $model, string $key): static
    {
        $class = $model::class;

        return new static("Meta [{$key}] isn't defined for [{$class}], so it can't be mass assigned. Define it with MetaFields::for(), or use setMeta().");
    }

    public static function unsupportedOperator(string $type, string $operator): static
    {
        return new static("Meta type [{$type}] can't be queried with [{$operator}].");
    }

    public static function unknownSortType(string $key): static
    {
        return new static("Meta [{$key}] isn't defined, so its column is unknown. Define it, or pass its type: orderByMeta('{$key}', 'desc', 'integer').");
    }
}
