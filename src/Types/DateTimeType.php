<?php

namespace Javaabu\Meta\Types;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\DateTimePicker;
use Javaabu\Stencil\Fields\Field;
use Throwable;

/**
 * A date and time, indexed. Date and time pickers. Read back as Carbon instances.
 */
class DateTimeType extends MetaType
{
    public const string STORAGE_FORMAT = 'Y-m-d H:i:s';

    public function name(): string
    {
        return 'datetime';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::DateTime;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        return $this->toDate($value, $key)->format(static::STORAGE_FORMAT);
    }

    public function unserialize(mixed $value): mixed
    {
        return Date::parse($value);
    }

    public function field(string $key): Field
    {
        return DateTimePicker::make($key);
    }

    public function matches(Field $field): bool
    {
        return $field instanceof DateTimePicker;
    }

    public function accepts(mixed $value): bool
    {
        return $value instanceof DateTimeInterface;
    }

    public function rules(): array
    {
        return ['date'];
    }

    protected function toDate(mixed $value, string $key): Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Date::instance($value);
        }

        if (! is_string($value) && ! is_int($value)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        try {
            return is_int($value) ? Date::createFromTimestamp($value) : Date::parse($value);
        } catch (Throwable) {
            throw MetaException::invalidValue($key, $this->name());
        }
    }
}
