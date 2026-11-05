<?php

namespace Javaabu\Meta;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Definitions\MetaField;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Forms\MetaForm;
use Javaabu\Meta\Support\Translatable\TranslatableMetaRecord;
use Javaabu\Meta\Support\Translations;
use Javaabu\Meta\Types\BooleanType;
use Javaabu\Meta\Types\DateTimeType;
use Javaabu\Meta\Types\DateType;
use Javaabu\Meta\Types\FloatType;
use Javaabu\Meta\Types\IntegerType;
use Javaabu\Meta\Types\JsonType;
use Javaabu\Meta\Types\MetaType;
use Javaabu\Meta\Types\StringType;
use Javaabu\Meta\Types\TextType;
use Javaabu\Meta\Types\TimeType;
use Javaabu\Stencil\Contracts\SavesRelationships;
use Javaabu\Stencil\Fields\Field;
use Javaabu\Stencil\Fields\FileUpload;
use Javaabu\Stencil\Form;
use WeakMap;

/**
 * The meta field definitions of every model, and the meta types.
 */
class MetaFields
{
    /**
     * The types every install has, in the order they're asked to match a field or value (last first).
     *
     * @var array<int, class-string<MetaType>>
     */
    public const array BUILT_IN_TYPES = [
        TextType::class,
        StringType::class,
        JsonType::class,
        FloatType::class,
        IntegerType::class,
        BooleanType::class,
        DateType::class,
        DateTimeType::class,
        TimeType::class,
    ];

    /**
     * Definitions as given, by model class and group ('' for the model's own fields).
     *
     * @var array<class-string, array<string, array<int, array<int|string, Field|string>|Closure>>>
     */
    protected array $sources = [];

    /** @var array<string, MetaType> */
    protected array $types = [];

    /** @var WeakMap<Field, string> */
    protected WeakMap $field_types;

    public function __construct()
    {
        $this->field_types = new WeakMap;

        foreach (static::BUILT_IN_TYPES as $type) {
            $this->type($type);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Definitions
    |--------------------------------------------------------------------------
    */

    /**
     * Define meta fields for a model, as stencil fields or key => type name:
     * [TextInput::make('subtitle'), Checkbox::make('featured'), 'views' => 'integer'].
     *
     * A group adds fields to some records of the model only (a post type): the
     * model's metaGroup() picks the group of a record. A closure is called each
     * time the fields are needed.
     *
     * @param  class-string<Model>  $model
     * @param  array<int|string, Field|string>|Closure(): array<int|string, Field|string>  $fields
     */
    public function for(string $model, array|Closure $fields, ?string $group = null): static
    {
        $this->sources[$model][$group ?? ''][] = $fields;

        return $this;
    }

    /**
     * Remove the definitions of a model (or one of its groups), or of every model.
     *
     * @param  class-string<Model>|null  $model
     */
    public function forget(?string $model = null, ?string $group = null): static
    {
        if ($model === null) {
            $this->sources = [];

            return $this;
        }

        if ($group === null) {
            unset($this->sources[$model]);

            return $this;
        }

        unset($this->sources[$model][$group]);

        return $this;
    }

    /**
     * The fields of a model, keyed by meta key: its own fields, then the group's.
     * For a record, the group is its metaGroup(). Definitions for a parent class
     * apply to its subclasses.
     *
     * @param  Model|class-string<Model>  $model
     * @return array<string, MetaField>
     */
    public function fields(Model|string $model, ?string $group = null): array
    {
        if ($group === null && $model instanceof Model && method_exists($model, 'metaGroup')) {
            $group = $model->metaGroup();
        }

        $class = is_string($model) ? $model : $model::class;

        $fields = $this->resolve($class, '');

        if ($group === null || $group === '') {
            return $fields;
        }

        return [...$fields, ...$this->resolve($class, $group)];
    }

    /**
     * One field of a model (or of a record's group).
     *
     * @param  Model|class-string<Model>  $model
     */
    public function field(Model|string $model, string $key, ?string $group = null): ?MetaField
    {
        return $this->fields($model, $group)[$key] ?? null;
    }

    /**
     * The meta keys of a model (or of a record's group).
     *
     * @param  Model|class-string<Model>  $model
     * @return array<int, string>
     */
    public function keys(Model|string $model, ?string $group = null): array
    {
        return array_keys($this->fields($model, $group));
    }

    /**
     * The groups defined for a model.
     *
     * @param  class-string<Model>  $model
     * @return array<int, string>
     */
    public function groups(string $model): array
    {
        $groups = [];

        foreach ($this->classChain($model) as $class) {
            array_push($groups, ...array_keys($this->sources[$class] ?? []));
        }

        return array_values(array_filter(array_unique($groups), fn (string $group) => $group !== ''));
    }

    /**
     * The field a query on a model uses for a key: the model's own field, or the
     * key's field in any group. Null when the key isn't defined. Throws when
     * groups define the key with different types.
     *
     * @param  class-string<Model>  $model
     */
    public function queryField(string $model, string $key): ?MetaField
    {
        $field = $this->resolve($model, '')[$key] ?? null;

        if ($field !== null) {
            return $field;
        }

        foreach ($this->groups($model) as $group) {
            $grouped = $this->resolve($model, $group)[$key] ?? null;

            if ($grouped === null) {
                continue;
            }

            if ($field !== null && $field->type->name() !== $grouped->type->name()) {
                throw MetaException::ambiguousKey($model, $key);
            }

            $field ??= $grouped;
        }

        return $field;
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string, MetaField>
     */
    protected function resolve(string $model, string $group): array
    {
        $fields = [];

        foreach ($this->classChain($model) as $class) {
            foreach ($this->sources[$class][$group] ?? [] as $source) {
                $source = $source instanceof Closure ? $source() : $source;

                foreach ($source as $key => $field) {
                    $definition = $this->define($key, $field, $group);

                    $fields[$definition->key] = $definition;
                }
            }
        }

        return $fields;
    }

    protected function define(int|string $key, Field|string $field, string $group): MetaField
    {
        if (is_string($field)) {
            $type = $this->getType($field);

            return new MetaField((string) $key, $type, $type->field((string) $key), false, $group ?: null);
        }

        if ($this->savesToRelationship($field)) {
            throw MetaException::unsupportedField($field->getName(), $field::class);
        }

        $type = $this->fieldType($field);

        // Fields are stateful in a form, so every definition gets its own copy.
        $copy = clone $field;

        if ($type->rules() !== []) {
            $copy->rules($type->rules());
        }

        return new MetaField($field->getName(), $type, $copy, $this->isDeclaredTranslatable($field), $group ?: null);
    }

    /**
     * Whether the field saves to a relationship or media collection instead of an attribute.
     */
    protected function savesToRelationship(Field $field): bool
    {
        if (method_exists($field, 'getRelationshipName') && $field->getRelationshipName() !== null) {
            return true;
        }

        if ($field instanceof FileUpload) {
            return $field->getCollection() !== null;
        }

        if (! $field instanceof SavesRelationships) {
            return false;
        }

        return ! $field->savesToAttribute();
    }

    /**
     * Whether ->translatable() was called on the field (and javaabu/translatable
     * is enabled). Stencil decides that for a record that translates the key, so
     * ask a copy of the field in a form bound to such a record.
     */
    protected function isDeclaredTranslatable(Field $field): bool
    {
        if (! Translations::enabled()) {
            return false;
        }

        $form = Form::make()->model(TranslatableMetaRecord::probe([$field->getName()]))->schema([clone $field]);

        return $form->getField($field->getName())?->isTranslatable() ?? false;
    }

    /**
     * The class and its parents, the furthest parent first.
     *
     * @return array<int, class-string>
     */
    protected function classChain(string $model): array
    {
        return [...array_reverse(array_values(class_parents($model) ?: [])), $model];
    }

    /**
     * The stencil form of a record's meta fields: MetaFields::form($post)->action(…).
     */
    public function form(Model $record, ?string $group = null): MetaForm
    {
        return MetaForm::for($record, $group);
    }

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    */

    /**
     * Register a meta type. A type with the name of an existing one replaces it.
     *
     * @param  MetaType|class-string<MetaType>  $type
     */
    public function type(MetaType|string $type): static
    {
        if (is_string($type)) {
            if (! is_a($type, MetaType::class, true)) {
                throw MetaException::invalidType($type);
            }

            $type = app($type);
        }

        unset($this->types[$type->name()]);

        $this->types[$type->name()] = $type;

        return $this;
    }

    public function getType(string $name): MetaType
    {
        return $this->types[$name] ?? throw MetaException::unknownType($name);
    }

    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * Every type, keyed by name, in registration order.
     *
     * @return array<string, MetaType>
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * Store a stencil field's values as a type instead of the one inferred from it: ->metaType('integer').
     */
    public function setFieldType(Field $field, string $type): void
    {
        $this->field_types[$field] = $type;
    }

    /**
     * The type of a stencil field: set with ->metaType(), or the last registered
     * type that matches it, or string.
     */
    public function fieldType(Field $field): MetaType
    {
        if (isset($this->field_types[$field])) {
            return $this->getType($this->field_types[$field]);
        }

        foreach (array_reverse($this->types) as $type) {
            if ($type->matches($field)) {
                return $type;
            }
        }

        return $this->getType('string');
    }

    /**
     * The type a value of an undefined key is stored as: the last registered type that accepts it.
     */
    public function typeForValue(mixed $value): MetaType
    {
        foreach (array_reverse($this->types) as $type) {
            if ($type->accepts($value)) {
                return $type;
            }
        }

        return $this->getType('string');
    }
}
