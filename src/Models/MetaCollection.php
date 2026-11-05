<?php

namespace Javaabu\Meta\Models;

use Illuminate\Database\Eloquent\Collection;
use Javaabu\Meta\Support\Translations;

/**
 * The meta rows of a record. Read a value by key, in the current locale:
 * $post->meta['subtitle'], or data_get($post, 'meta.subtitle'), which is how
 * stencil fields named meta.subtitle get their value.
 *
 * @template TKey of array-key
 * @template TModel of Meta
 *
 * @extends Collection<TKey, TModel>
 */
class MetaCollection extends Collection
{
    /**
     * The row of a key in a locale, falling back to the default locale and then
     * the row without a locale. A locale of '' reads the row without a locale only.
     */
    public function findMeta(string $key, ?string $locale = null, bool $fallback = true): ?Meta
    {
        $rows = [];

        foreach ($this->items as $row) {
            if ($row->key === $key) {
                $rows[$row->locale] = $row;
            }
        }

        if ($rows === []) {
            return null;
        }

        if (! $fallback) {
            return $rows[$locale ?? ''] ?? null;
        }

        foreach (Translations::fallbackChain($locale) as $candidate) {
            if (isset($rows[$candidate])) {
                return $rows[$candidate];
            }
        }

        return reset($rows) ?: null;
    }

    /**
     * The value of a key in a locale (the current one by default), with fallbacks.
     */
    public function metaValue(string $key, mixed $default = null, ?string $locale = null): mixed
    {
        $row = $this->findMeta($key, $locale);

        return $row === null ? value($default) : $row->getValue();
    }

    /**
     * Whether there's a value for the key, in any locale.
     */
    public function hasMeta(string $key): bool
    {
        return $this->contains(fn (Meta $row) => $row->key === $key);
    }

    /**
     * The values of a key by locale: ['en' => 'Malé', 'dv' => 'މާލެ'], with '' for the value without a locale.
     *
     * @return array<string, mixed>
     */
    public function translations(string $key): array
    {
        $values = [];

        foreach ($this->items as $row) {
            if ($row->key === $key) {
                $values[$row->locale] = $row->getValue();
            }
        }

        return $values;
    }

    /**
     * Every value by key, in the current locale.
     *
     * @return array<string, mixed>
     */
    public function toValues(): array
    {
        $values = [];

        foreach (array_unique($this->pluck('key')->all()) as $key) {
            $values[$key] = $this->metaValue($key);
        }

        return $values;
    }

    /**
     * Whether the collection has the offsets. A string offset is a meta key, so
     * Arr::exists() and data_get() see meta keys.
     *
     * @param  TKey|array<array-key, TKey>  $key
     */
    public function has($key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! $this->offsetExists($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A string offset is a meta key; integer offsets are rows, as usual.
     */
    public function offsetExists($key): bool
    {
        if (is_string($key) && ! array_key_exists($key, $this->items)) {
            return $this->hasMeta($key) || $this->hasTranslationInput($key);
        }

        return parent::offsetExists($key);
    }

    public function offsetGet($key): mixed
    {
        if (is_string($key) && ! array_key_exists($key, $this->items)) {
            return $this->translationInputValue($key) ?? $this->metaValue($key);
        }

        return parent::offsetGet($key);
    }

    /**
     * Whether a key is a translatable input name of a stored key: subtitle_dv.
     */
    protected function hasTranslationInput(string $key): bool
    {
        return $this->splitTranslationInput($key) !== null;
    }

    protected function translationInputValue(string $key): mixed
    {
        $split = $this->splitTranslationInput($key);

        if ($split === null) {
            return null;
        }

        return $this->findMeta($split[0], $split[1], fallback: false)?->getValue();
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected function splitTranslationInput(string $key): ?array
    {
        if ($this->hasMeta($key)) {
            return null;
        }

        foreach ($this->items as $row) {
            if ($row->locale !== '' && $key === "{$row->key}_{$row->locale}") {
                return [$row->key, $row->locale];
            }
        }

        return null;
    }
}
