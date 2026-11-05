# Extending javaabu/meta

## A custom type

```php
<?php

namespace App\Meta;

use App\Forms\ColorPicker;
use App\Support\Color;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Types\MetaType;
use Javaabu\Stencil\Fields\Field;

class ColorType extends MetaType
{
    public function name(): string
    {
        return 'color';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::String;   // indexed: String, Integer, Float, DateTime. Not indexed: Text, Json
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
        return ColorPicker::make($key);
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
```

- Register it with `MetaFields::type(ColorType::class)` or `config('meta.types')`. Types registered later are asked first in `matches()`/`accepts()`, and the same name replaces a type.
- Override `where(Builder $query, string $operator, mixed $value, string $key = '')` and `whereIn(...)` to query differently. They get the query on the meta table, already constrained to the model and key.

## Translatable meta (javaabu/translatable v3, optional)

```php
MetaFields::for(Post::class, [TextInput::make('subtitle')->required()->translatable()]);

$post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);
$post->setMeta('subtitle', 'މާލެ', locale: 'dv');
$post->getMeta('subtitle');                          // current locale → default → value without a locale
$post->getMetaTranslation('subtitle', 'dv', fallback: false);
$post->getMetaTranslations('subtitle');
```

- **When it applies:** `->translatable()` is safe without the package. It only applies when translatable is enabled with active languages.
- **Storage:** one row per locale. Values that aren't translatable use the locale `''`.
- **Forms:** one input per locale (`subtitle_en`, `subtitle_dv`), in tabs or `layout: 'stacked'`. The default locale keeps the rules and the others become optional (`require_all: true` changes that). Mass assignment takes the same names.
- **Queries:** match in any locale and sort in the current locale.
- **Turning it off:** `Javaabu\Meta\Support\Translations::$enabled = false` turns translatable meta off.

## Storage and performance

- **The table:** one shared `meta` table with typed columns. The indexes are `(metable_type, key, value_*, metable_id)` for conditions and a unique `(metable_type, metable_id, key, locale)` for reads and sorts.
- **Conditions:** compiled to `id IN (subquery)`. A selective one takes under 1 ms on 800k rows (MySQL and SQLite).
- **Sorting:** sorting a whole large table by meta with no selective filter takes about 380 ms on MySQL at 100k records. Use a real column for keys you always sort by.
- **A table per model:** `protected string $meta_table = 'post_meta';` plus `MetaTable::columns($table, 'post_meta')` in a migration. It's 10–40% faster and smaller.
- **UUID or ULID keys:** set `meta.morph_key` before migrating.
