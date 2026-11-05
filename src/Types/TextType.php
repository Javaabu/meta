<?php

namespace Javaabu\Meta\Types;

use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\Wysiwyg;
use Stringable;

/**
 * Long text, not indexed. Textareas and rich text.
 */
class TextType extends MetaType
{
    public function name(): string
    {
        return 'text';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::Text;
    }

    public function serialize(mixed $value, string $key = ''): mixed
    {
        if (! is_scalar($value) && ! $value instanceof Stringable) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return (string) $value;
    }

    public function unserialize(mixed $value): mixed
    {
        return (string) $value;
    }

    public function field(string $key): Field
    {
        return Textarea::make($key);
    }

    public function matches(Field $field): bool
    {
        return $field instanceof Textarea || $field instanceof Wysiwyg;
    }

    public function accepts(mixed $value): bool
    {
        return is_string($value) || $value instanceof Stringable;
    }
}
