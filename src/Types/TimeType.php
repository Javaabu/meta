<?php

namespace Javaabu\Meta\Types;

use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TimePicker;
use Throwable;

/**
 * A time of day, stored as H:i:s in the string column, so it sorts and compares. Time pickers.
 */
class TimeType extends MetaType
{
    public function name(): string
    {
        return 'time';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::String;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (! is_string($value)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        try {
            return Date::parse($value)->format('H:i:s');
        } catch (Throwable) {
            throw MetaException::invalidValue($key, $this->name());
        }
    }

    public function unserialize(mixed $value): mixed
    {
        return (string) $value;
    }

    public function field(string $key): Field
    {
        return TimePicker::make($key);
    }

    public function matches(Field $field): bool
    {
        return $field instanceof TimePicker;
    }
}
