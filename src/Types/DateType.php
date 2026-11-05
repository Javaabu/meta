<?php

namespace Javaabu\Meta\Types;

use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\DateTimePicker;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TimePicker;

/**
 * A date without a time, stored at midnight in the datetime column. Date pickers.
 */
class DateType extends DateTimeType
{
    public function name(): string
    {
        return 'date';
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        return $this->toDate($value, $key)->startOfDay()->format(static::STORAGE_FORMAT);
    }

    public function unserialize(mixed $value): mixed
    {
        return parent::unserialize($value)->startOfDay();
    }

    public function field(string $key): Field
    {
        return DatePicker::make($key);
    }

    public function matches(Field $field): bool
    {
        if ($field instanceof DateTimePicker || $field instanceof TimePicker) {
            return false;
        }

        return $field instanceof DatePicker;
    }

    public function accepts(mixed $value): bool
    {
        return false;
    }
}
