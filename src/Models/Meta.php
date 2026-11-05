<?php

namespace Javaabu\Meta\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\MetaFields;
use Javaabu\Meta\Types\MetaType;

/**
 * One meta value of a record: its key, locale ('' unless translatable), type and value.
 *
 * @property string $metable_type
 * @property int|string $metable_id
 * @property string $key
 * @property string $locale
 * @property string $type
 */
class Meta extends Model
{
    protected $guarded = [];

    /**
     * The table from config('meta.table'), unless a model with its own meta table set one.
     */
    public function getTable(): string
    {
        return $this->table ?? (string) config('meta.table', 'meta');
    }

    public function metable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  array<int, static>  $models
     * @return MetaCollection<int, static>
     */
    public function newCollection(array $models = []): MetaCollection
    {
        return new MetaCollection($models);
    }

    public function getMetaType(): MetaType
    {
        return app(MetaFields::class)->getType($this->type);
    }

    /**
     * The value, cast by its type.
     */
    public function getValue(): mixed
    {
        $type = $this->getMetaType();
        $stored = $this->getAttribute($type->column()->value);

        return $stored === null ? null : $type->unserialize($stored);
    }

    /**
     * Store a value as a type. Clears the other value columns.
     */
    public function setValue(MetaType $type, mixed $value): static
    {
        $this->setAttribute('type', $type->name());

        foreach (MetaColumn::cases() as $column) {
            $this->setAttribute($column->value, null);
        }

        $this->setAttribute($type->column()->value, $type->serialize($value, (string) $this->key));

        return $this;
    }
}
