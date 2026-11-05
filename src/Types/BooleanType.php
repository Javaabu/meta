<?php

namespace Javaabu\Meta\Types;

use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\Field;

/**
 * True or false, stored as 1 or 0 in the integer column. Checkboxes.
 */
class BooleanType extends MetaType
{
    public function name(): string
    {
        return 'boolean';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::Integer;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        return $value ? 1 : 0;
    }

    public function unserialize(mixed $value): mixed
    {
        return (bool) $value;
    }

    public function field(string $key): Field
    {
        return Checkbox::make($key);
    }

    public function matches(Field $field): bool
    {
        return $field instanceof Checkbox;
    }

    public function accepts(mixed $value): bool
    {
        return is_bool($value);
    }
}
