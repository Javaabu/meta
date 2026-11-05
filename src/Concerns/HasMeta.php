<?php

namespace Javaabu\Meta\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Javaabu\Meta\Definitions\MetaField;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\MetaFields;
use Javaabu\Meta\Models\Meta;
use Javaabu\Meta\Models\MetaCollection;
use Javaabu\Meta\Query\MetaQuery;
use Javaabu\Meta\Support\Translations;

/**
 * Meta values for an Eloquent model, stored in typed, indexed columns of the
 * meta table (or the model's own $meta_table).
 *
 * @property-read MetaCollection<int, Meta> $meta
 *
 * @method static Builder<static> whereMeta(string $key, mixed $operator = null, mixed $value = null)
 * @method static Builder<static> orWhereMeta(string $key, mixed $operator = null, mixed $value = null)
 * @method static Builder<static> whereMetaIn(string $key, iterable<int, mixed> $values)
 * @method static Builder<static> orWhereMetaIn(string $key, iterable<int, mixed> $values)
 * @method static Builder<static> whereMetaNotIn(string $key, iterable<int, mixed> $values)
 * @method static Builder<static> orWhereMetaNotIn(string $key, iterable<int, mixed> $values)
 * @method static Builder<static> whereHasMeta(string|array<int, string> $keys)
 * @method static Builder<static> orWhereHasMeta(string|array<int, string> $keys)
 * @method static Builder<static> whereDoesntHaveMeta(string|array<int, string> $keys)
 * @method static Builder<static> orWhereDoesntHaveMeta(string|array<int, string> $keys)
 * @method static Builder<static> orderByMeta(string $key, string $direction = 'asc', ?string $type = null)
 * @method static Builder<static> orderByMetaDesc(string $key, ?string $type = null)
 * @method static Builder<static> withMeta(?array<int, string> $keys = null)
 */
trait HasMeta
{
    /**
     * Meta assigned before the record was saved, or through the meta attribute,
     * written after the record is saved.
     *
     * @var array<int, array{values: array<string, mixed>, locale: string|null, mass_assigned: bool}>
     */
    protected array $pending_meta = [];

    public static function bootHasMeta(): void
    {
        static::saved(function (Model $model) {
            $model->savePendingMeta();
        });

        static::deleted(function (Model $model) {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->meta()->delete();
        });
    }

    /**
     * Makes meta mass assignable on models with $fillable.
     */
    public function initializeHasMeta(): void
    {
        $fillable = $this->getFillable();

        if ($fillable === [] || in_array('meta', $fillable, true)) {
            return;
        }

        $this->mergeFillable(['meta']);
    }

    /*
    |--------------------------------------------------------------------------
    | Definitions
    |--------------------------------------------------------------------------
    */

    /**
     * The meta table of this model. Set a $meta_table property to use a table of your own.
     */
    public function getMetaTable(): string
    {
        if (property_exists($this, 'meta_table') && $this->meta_table) {
            return $this->meta_table;
        }

        return (string) config('meta.table', 'meta');
    }

    /**
     * The group whose fields this record has, besides the model's own: its post
     * type, for example. Override it to return an attribute: return $this->type.
     */
    public function metaGroup(): ?string
    {
        return null;
    }

    /**
     * The meta fields of this record, keyed by key.
     *
     * @return array<string, MetaField>
     */
    public function metaFields(): array
    {
        return app(MetaFields::class)->fields($this);
    }

    public function metaField(string $key): ?MetaField
    {
        return $this->metaFields()[$key] ?? null;
    }

    /*
    |--------------------------------------------------------------------------
    | Relation
    |--------------------------------------------------------------------------
    */

    /**
     * Every meta row of the record. Eager load it to read meta without a query per record: Post::with('meta').
     *
     * @return MorphMany<Meta, $this>
     */
    public function meta(): MorphMany
    {
        /** @var class-string<Meta> $class */
        $class = config('meta.model', Meta::class);

        $instance = new $class;
        $instance->setTable($this->getMetaTable());

        $table = $instance->getTable();

        return $this->newMorphMany(
            $instance->newQuery(),
            $this,
            "{$table}.metable_type",
            "{$table}.metable_id",
            $this->getKeyName(),
        );
    }

    /**
     * The loaded meta rows, loading them first if they aren't.
     *
     * @return MetaCollection<int, Meta>
     */
    public function metaRows(): MetaCollection
    {
        $rows = $this->getRelationValue('meta');

        return $rows instanceof MetaCollection ? $rows : new MetaCollection($rows?->all() ?? []);
    }

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * A meta value in the current locale (or the given one), falling back to the
     * default locale. The default when there's none.
     */
    public function getMeta(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        return $this->metaRows()->metaValue($key, $default, $locale);
    }

    /**
     * A meta value in a locale. Without $fallback, null when that locale has none.
     */
    public function getMetaTranslation(string $key, string $locale, bool $fallback = true): mixed
    {
        if ($fallback) {
            return $this->getMeta($key, locale: $locale);
        }

        return $this->metaRows()->findMeta($key, $locale, fallback: false)?->getValue();
    }

    /**
     * A meta value by locale: ['en' => 'Malé', 'dv' => 'މާލެ']. A value saved without a locale is under ''.
     *
     * @return array<string, mixed>
     */
    public function getMetaTranslations(string $key): array
    {
        return $this->metaRows()->translations($key);
    }

    public function hasMeta(string $key): bool
    {
        return $this->metaRows()->hasMeta($key);
    }

    /**
     * Every meta value, keyed by key, in the current locale.
     *
     * @return array<string, mixed>
     */
    public function getAllMeta(): array
    {
        return $this->metaRows()->toValues();
    }

    /*
    |--------------------------------------------------------------------------
    | Writing
    |--------------------------------------------------------------------------
    */

    /**
     * Save meta values: setMeta('subtitle', 'ސުރުހީ') or setMeta(['subtitle' => …, 'views' => 10]).
     * Written straight away for a saved record, and when it's saved otherwise. A
     * null value removes the key. A translatable field takes a locale map
     * (['en' => …, 'dv' => …]), or a value in $locale or the current locale.
     *
     * @param  string|array<string, mixed>  $key
     */
    public function setMeta(string|array $key, mixed $value = null, ?string $locale = null): static
    {
        $values = is_array($key) ? $key : [$key => $value];

        if (! $this->exists) {
            $this->pending_meta[] = ['values' => $values, 'locale' => $locale, 'mass_assigned' => false];

            return $this;
        }

        $this->writeMeta($values, $locale);

        return $this;
    }

    /**
     * Remove meta keys, in every locale or in one.
     *
     * @param  string|array<int, string>  $keys
     */
    public function removeMeta(string|array $keys, ?string $locale = null): static
    {
        $keys = (array) $keys;

        if (! $this->exists) {
            $this->pending_meta[] = ['values' => array_fill_keys($keys, null), 'locale' => $locale, 'mass_assigned' => false];

            return $this;
        }

        $this->deleteMetaRows(array_fill_keys($keys, $locale === null ? null : [$locale]));
        $this->refreshMetaRelation();

        return $this;
    }

    /**
     * Save the values and remove every other key.
     *
     * @param  array<string, mixed>  $values
     */
    public function syncMeta(array $values): static
    {
        if (! $this->exists) {
            return $this->setMeta($values);
        }

        $stale = array_diff(array_unique($this->meta()->pluck('key')->all()), array_keys($values));

        $this->getConnection()->transaction(function () use ($values, $stale) {
            if ($stale !== []) {
                $this->deleteMetaRows(array_fill_keys($stale, null));
            }

            $this->writeMeta($values);
        });

        return $this;
    }

    /**
     * $post->meta = [...], fill(['meta' => [...]]) and create(['meta' => [...]]):
     * written when the record is saved. Only defined keys (and the per-locale
     * inputs of translatable ones, subtitle_dv) are taken.
     *
     * @param  array<string, mixed>|null  $value
     */
    public function setMetaAttribute(?array $value): void
    {
        if ($value === null) {
            return;
        }

        $this->pending_meta[] = ['values' => $value, 'locale' => null, 'mass_assigned' => true];
    }

    /**
     * Write the meta assigned before the record was saved. Called when it's saved.
     */
    public function savePendingMeta(): void
    {
        $pending = $this->pending_meta;
        $this->pending_meta = [];

        foreach ($pending as $entry) {
            $values = $entry['mass_assigned']
                ? $this->fillableMeta($entry['values'])
                : $entry['values'];

            if ($values !== []) {
                $this->writeMeta($values, $entry['locale']);
            }
        }
    }

    /**
     * Whether meta is waiting for the record to be saved.
     */
    public function hasPendingMeta(): bool
    {
        return $this->pending_meta !== [];
    }

    /**
     * The defined keys of mass assigned meta. Per-locale inputs of translatable
     * fields (subtitle_dv) become locale maps.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function fillableMeta(array $values): array
    {
        $fields = $this->metaFields();
        $fillable = [];
        $translations = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (isset($fields[$key])) {
                $fillable[$key] = $value;

                continue;
            }

            $input = $this->translatableMetaInput($key, $fields);

            if ($input !== null) {
                $translations[$input[0]][$input[1]] = $value;

                continue;
            }

            if (static::preventsSilentlyDiscardingAttributes()) {
                throw MetaException::notFillable($this, $key);
            }
        }

        foreach ($translations as $key => $locales) {
            $base = $fillable[$key] ?? null;

            $fillable[$key] = Translations::isLocaleMap($base)
                ? [...$base, ...$locales]
                : $locales;
        }

        return $fillable;
    }

    /**
     * The key and locale of a translatable field's input name: subtitle_dv → [subtitle, dv].
     *
     * @param  array<string, MetaField>  $fields
     * @return array{0: string, 1: string}|null
     */
    protected function translatableMetaInput(string $key, array $fields): ?array
    {
        if (! Translations::enabled()) {
            return null;
        }

        foreach ($fields as $field) {
            if (! $field->translatable) {
                continue;
            }

            foreach (Translations::locales() as $locale) {
                if ($key === "{$field->key}_{$locale}") {
                    return [$field->key, $locale];
                }
            }
        }

        return null;
    }

    /**
     * Upsert the values and delete the null ones, in one transaction.
     *
     * @param  array<string, mixed>  $values
     */
    protected function writeMeta(array $values, ?string $locale = null): void
    {
        $registry = app(MetaFields::class);
        $fields = $this->metaFields();
        $rows = [];
        $deletes = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;
            $field = $fields[$key] ?? null;

            foreach ($this->metaValuesByLocale($field, $value, $locale) as $row_locale => $row_value) {
                if ($row_value === null) {
                    $deletes[$key][] = $row_locale;

                    continue;
                }

                $type = $field?->type ?? $registry->typeForValue($row_value);

                $row = (new Meta)->forceFill(['key' => $key])->setValue($type, $row_value);

                $rows[] = [
                    'metable_type' => $this->getMorphClass(),
                    'metable_id' => $this->getKey(),
                    'key' => $key,
                    'locale' => (string) $row_locale,
                    ...$row->only(['type', ...MetaColumn::names()]),
                ];
            }
        }

        if ($rows === [] && $deletes === []) {
            return;
        }

        $this->getConnection()->transaction(function () use ($rows, $deletes) {
            if ($deletes !== []) {
                $this->deleteMetaRows($deletes);
            }

            if ($rows !== []) {
                $this->meta()->getRelated()->newQuery()->upsert(
                    $rows,
                    ['metable_type', 'metable_id', 'key', 'locale'],
                    ['type', ...MetaColumn::names()],
                );
            }
        });

        $this->refreshMetaRelation();
    }

    /**
     * The value to store in each locale: a translatable field's locale map, a
     * value in the given locale, or a value without a locale.
     *
     * @return array<string, mixed>
     */
    protected function metaValuesByLocale(?MetaField $field, mixed $value, ?string $locale): array
    {
        if ($locale !== null) {
            return [$locale => $value];
        }

        if (! $field?->translatable || ! Translations::enabled()) {
            return ['' => $value];
        }

        if (Translations::isLocaleMap($value)) {
            return $value;
        }

        return [Translations::currentLocale() => $value];
    }

    /**
     * @param  array<string, array<int, string>|null>  $deletes  key => locales (null for every locale)
     */
    protected function deleteMetaRows(array $deletes): void
    {
        $this->meta()->where(function ($query) use ($deletes) {
            foreach ($deletes as $key => $locales) {
                $query->orWhere(function ($query) use ($key, $locales) {
                    $query->where('key', $key);

                    if ($locales !== null) {
                        $query->whereIn('locale', $locales);
                    }
                });
            }
        })->delete();
    }

    /**
     * Reload loaded meta so reads see the writes.
     */
    protected function refreshMetaRelation(): void
    {
        if (! $this->relationLoaded('meta')) {
            return;
        }

        $this->setRelation('meta', $this->meta()->get());
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Records whose meta value matches: whereMeta('featured', true), whereMeta('views', '>', 100).
     */
    public function scopeWhereMeta(Builder $query, string $key, mixed $operator = null, mixed $value = null): void
    {
        [$value, $operator] = $query->getQuery()->prepareValueAndOperator($value, $operator, func_num_args() === 3);

        MetaQuery::where($query, $key, $operator, $value);
    }

    public function scopeOrWhereMeta(Builder $query, string $key, mixed $operator = null, mixed $value = null): void
    {
        [$value, $operator] = $query->getQuery()->prepareValueAndOperator($value, $operator, func_num_args() === 3);

        MetaQuery::where($query, $key, $operator, $value, 'or');
    }

    /**
     * Records whose meta value is one of the values.
     *
     * @param  iterable<int, mixed>  $values
     */
    public function scopeWhereMetaIn(Builder $query, string $key, iterable $values): void
    {
        MetaQuery::whereIn($query, $key, $values);
    }

    /**
     * @param  iterable<int, mixed>  $values
     */
    public function scopeOrWhereMetaIn(Builder $query, string $key, iterable $values): void
    {
        MetaQuery::whereIn($query, $key, $values, 'or');
    }

    /**
     * Records that have the key with a value that isn't one of the values.
     *
     * @param  iterable<int, mixed>  $values
     */
    public function scopeWhereMetaNotIn(Builder $query, string $key, iterable $values): void
    {
        MetaQuery::whereIn($query, $key, $values, not: true);
    }

    /**
     * @param  iterable<int, mixed>  $values
     */
    public function scopeOrWhereMetaNotIn(Builder $query, string $key, iterable $values): void
    {
        MetaQuery::whereIn($query, $key, $values, 'or', true);
    }

    /**
     * Records that have a value for every key.
     *
     * @param  string|array<int, string>  $keys
     */
    public function scopeWhereHasMeta(Builder $query, string|array $keys): void
    {
        $query->where(function (Builder $query) use ($keys) {
            foreach ((array) $keys as $key) {
                MetaQuery::whereHas($query, $key);
            }
        });
    }

    /**
     * @param  string|array<int, string>  $keys
     */
    public function scopeOrWhereHasMeta(Builder $query, string|array $keys): void
    {
        $query->orWhere(function (Builder $query) use ($keys) {
            foreach ((array) $keys as $key) {
                MetaQuery::whereHas($query, $key);
            }
        });
    }

    /**
     * Records that have none of the keys.
     *
     * @param  string|array<int, string>  $keys
     */
    public function scopeWhereDoesntHaveMeta(Builder $query, string|array $keys): void
    {
        $query->where(function (Builder $query) use ($keys) {
            foreach ((array) $keys as $key) {
                MetaQuery::whereHas($query, $key, not: true);
            }
        });
    }

    /**
     * @param  string|array<int, string>  $keys
     */
    public function scopeOrWhereDoesntHaveMeta(Builder $query, string|array $keys): void
    {
        $query->orWhere(function (Builder $query) use ($keys) {
            foreach ((array) $keys as $key) {
                MetaQuery::whereHas($query, $key, not: true);
            }
        });
    }

    /**
     * Sort by a meta value. Keys that aren't defined need their type: orderByMeta('views', 'desc', 'integer').
     */
    public function scopeOrderByMeta(Builder $query, string $key, string $direction = 'asc', ?string $type = null): void
    {
        MetaQuery::orderBy($query, $key, $direction, $type);
    }

    public function scopeOrderByMetaDesc(Builder $query, string $key, ?string $type = null): void
    {
        MetaQuery::orderBy($query, $key, 'desc', $type);
    }

    /**
     * Eager load meta, every key or only some.
     *
     * @param  array<int, string>|null  $keys
     */
    public function scopeWithMeta(Builder $query, ?array $keys = null): void
    {
        if ($keys === null) {
            $query->with('meta');

            return;
        }

        $query->with(['meta' => fn ($meta) => $meta->whereIn('key', $keys)]);
    }
}
