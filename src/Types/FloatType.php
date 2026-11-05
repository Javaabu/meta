<?php

namespace Javaabu\Meta\Types;

use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TextInput;

/**
 * Numbers with decimals, indexed. Numeric text inputs.
 */
class FloatType extends MetaType
{
    public function name(): string
    {
        return 'float';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::Float;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if (! is_numeric($value)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return (float) $value;
    }

    public function unserialize(mixed $value): mixed
    {
        return (float) $value;
    }

    public function field(string $key): Field
    {
        return TextInput::make($key)->numeric();
    }

    public function matches(Field $field): bool
    {
        return $field instanceof TextInput && $field->isNumeric();
    }

    public function accepts(mixed $value): bool
    {
        return is_float($value);
    }

    public function rules(): array
    {
        return ['numeric'];
    }
}
