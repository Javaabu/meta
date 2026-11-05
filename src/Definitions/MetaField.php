<?php

namespace Javaabu\Meta\Definitions;

use Javaabu\Meta\Types\MetaType;
use Javaabu\Stencil\Fields\Field;

/**
 * A defined meta field: its key, type, the stencil field that edits it, and
 * whether its values are stored per locale.
 */
final readonly class MetaField
{
    public function __construct(
        public string $key,
        public MetaType $type,
        public Field $field,
        public bool $translatable = false,
        public ?string $group = null,
    ) {}

    /**
     * The validation rules of the value, from the type. The stencil field adds its own when it's in a form.
     *
     * @return array<int, mixed>
     */
    public function typeRules(): array
    {
        return $this->type->rules();
    }
}
