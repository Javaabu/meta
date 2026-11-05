<?php

namespace Javaabu\Meta\Types;

use BackedEnum;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\Hidden;
use Javaabu\Stencil\Fields\Radio;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;
use Stringable;
use UnitEnum;

/**
 * Up to 255 characters, indexed. Text inputs, single selects, radios and hidden fields.
 */
class StringType extends MetaType
{
    public const int MAX_LENGTH = 255;

    public function name(): string
    {
        return 'string';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::String;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        $value = match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => throw MetaException::invalidValue($key, $this->name()),
        };

        if (mb_strlen($value) > static::MAX_LENGTH) {
            throw MetaException::valueTooLong($key, static::MAX_LENGTH);
        }

        return $value;
    }

    public function unserialize(mixed $value): mixed
    {
        return (string) $value;
    }

    public function matches(Field $field): bool
    {
        if ($field instanceof TextInput) {
            return ! $field->isNumeric();
        }

        if ($field instanceof Select) {
            return ! $field->isMultiple();
        }

        return $field instanceof Radio || $field instanceof Hidden;
    }

    public function accepts(mixed $value): bool
    {
        if ($value instanceof BackedEnum) {
            return is_string($value->value);
        }

        if ($value instanceof UnitEnum) {
            return true;
        }

        if (! is_string($value) && ! $value instanceof Stringable) {
            return false;
        }

        return mb_strlen((string) $value) <= static::MAX_LENGTH;
    }

    public function rules(): array
    {
        return ['max:' . static::MAX_LENGTH];
    }
}
