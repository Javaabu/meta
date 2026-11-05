<?php

namespace Javaabu\Meta\Forms;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Definitions\MetaField;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\MetaFields;
use Javaabu\Meta\Support\Translatable\TranslatableMetaRecord;
use Javaabu\Meta\Support\Translations;
use Javaabu\Stencil\Form;

/**
 * The stencil form of a record's meta fields: its fields come from the
 * definitions, its values from the record's meta, and saving it saves the meta.
 *
 * ```php
 * $form = MetaForm::for($post)->action(route('posts.meta.update', $post));
 *
 * MetaForm::for($post)->save(); // validates the request and saves the meta
 * ```
 *
 * Render it like any stencil form ({{ $form }}, or as an Inertia prop), or
 * ->embedded() inside the record's own <form>.
 */
class MetaForm extends Form
{
    protected ?Model $metable = null;

    protected ?string $group = null;

    /**
     * The form of a record's meta fields (or of one of the model's groups), filled with its values.
     */
    public static function for(Model $record, ?string $group = null, ?string $id = null): static
    {
        $form = static::make($id ?? 'meta-form');

        $form->metable = $record;
        $form->group = $group;

        return $form->model($form->metaRecord());
    }

    protected function setUp(): void
    {
        $this->submit(__('meta::strings.save'));
    }

    protected function definition(): array
    {
        return array_values(array_map(fn (MetaField $field) => $field->field, $this->metaFields()));
    }

    /**
     * @return array<string, MetaField>
     */
    public function metaFields(): array
    {
        return app(MetaFields::class)->fields($this->getMetable(), $this->group);
    }

    /**
     * The record whose meta the form edits.
     */
    public function getMetable(): Model
    {
        if (! $this->metable) {
            throw MetaException::formWithoutRecord();
        }

        return $this->metable;
    }

    /**
     * What stencil binds to: the meta values by key, or (with translatable fields
     * and javaabu/translatable enabled) a record that holds every locale's value.
     *
     * @return array<string, mixed>|TranslatableMetaRecord
     */
    public function metaRecord(): array|TranslatableMetaRecord
    {
        $metable = $this->getMetable();
        $values = [];
        $translatable = [];

        foreach ($this->metaFields() as $key => $field) {
            if ($field->translatable && Translations::enabled()) {
                $translatable[] = $key;
                $values[$key] = $metable->getMetaTranslations($key);

                continue;
            }

            $values[$key] = $metable->getMeta($key);
        }

        if ($translatable === []) {
            return $values;
        }

        return TranslatableMetaRecord::fromValues($values, $translatable);
    }

    /**
     * Validate the request (or take validated data) and save the meta. A record
     * that isn't saved yet gets its meta when it's saved.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function save(?array $data = null): Model
    {
        $data ??= $this->validate();

        $metable = $this->getMetable();
        $metable->fillMeta($data);

        $this->model($this->metaRecord());

        return $metable;
    }
}
