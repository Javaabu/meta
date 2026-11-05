<?php

namespace Javaabu\Meta\Types;

use BackedEnum;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TextInput;

/**
 * Whole numbers, indexed. Integer text inputs.
 */
class IntegerType extends MetaType
{
    public function name(): string
    {
        return 'integer';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::Integer;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (! is_numeric($value)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return (int) $value;
    }

    public function unserialize(mixed $value): mixed
    {
        return (int) $value;
    }

    public function field(string $key): Field
    {
        return TextInput::make($key)->integer();
    }

    public function matches(Field $field): bool
    {
        return $field instanceof TextInput && $field->isInteger();
    }

    public function accepts(mixed $value): bool
    {
        if ($value instanceof BackedEnum) {
            return is_int($value->value);
        }

        return is_int($value);
    }

    public function rules(): array
    {
        return ['integer'];
    }
}
