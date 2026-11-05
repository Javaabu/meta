<?php

namespace Javaabu\Meta\Tests\Fixtures;

use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Types\MetaType;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\TextInput;

/**
 * A hex colour, stored upper case in the string column and read back as a Color.
 */
class ColorType extends MetaType
{
    public function name(): string
    {
        return 'color';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::String;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        $hex = strtoupper(ltrim((string) $value, '#'));

        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return "#{$hex}";
    }

    public function unserialize(mixed $value): mixed
    {
        return new Color($value);
    }

    public function field(string $key): Field
    {
        return TextInput::make($key)->placeholder('#000000');
    }

    public function matches(Field $field): bool
    {
        return $field instanceof ColorPicker;
    }

    public function accepts(mixed $value): bool
    {
        return $value instanceof Color;
    }

    public function rules(): array
    {
        return ['regex:/^#?[0-9A-Fa-f]{6}$/'];
    }
}
