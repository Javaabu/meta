---
title: Custom meta types
sidebar_position: 1
---

A meta type decides how a value is stored, read, queried and edited. To add one, extend `Javaabu\Meta\Types\MetaType`.

This one stores hex colours upper case in the indexed string column and reads them back as a `Color` value object. A `ColorPicker` stencil field edits them:

```php
namespace App\Meta;

use App\Forms\ColorPicker;
use App\Support\Color;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Types\MetaType;
use Javaabu\Stencil\Fields\Field;

class ColorType extends MetaType
{
    /** Stored with each value and used in definitions: 'accent' => 'color' */
    public function name(): string
    {
        return 'color';
    }

    public function column(): MetaColumn
    {
        return MetaColumn::String;
    }

    /** The value as stored in the column. Never null. */
    public function serialize(mixed $value, string $key = ''): mixed
    {
        $hex = strtoupper(ltrim((string) $value, '#'));

        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            throw MetaException::invalidValue($key, $this->name());
        }

        return "#{$hex}";
    }

    /** The stored value as your code gets it. Never null. */
    public function unserialize(mixed $value): mixed
    {
        return new Color($value);
    }

    /** The field for keys defined only by type: MetaFields::for(Post::class, ['accent' => 'color']) */
    public function field(string $key): Field
    {
        return ColorPicker::make($key);
    }

    /** Stencil fields that store their values as this type (inferred without ->metaType()) */
    public function matches(Field $field): bool
    {
        return $field instanceof ColorPicker;
    }

    /** Values of undefined keys that are stored as this type: $post->setMeta('tint', new Color('#123456')) */
    public function accepts(mixed $value): bool
    {
        return $value instanceof Color;
    }

    /** Added to the field's own rules in meta forms */
    public function rules(): array
    {
        return ['regex:/^#?[0-9A-Fa-f]{6}$/'];
    }
}
```

Register it in a service provider, or in `config('meta.types')`:

```php
MetaFields::type(ColorType::class);
```

```php
// config/meta.php
'types' => [App\Meta\ColorType::class],
```

Then use it like a built-in type:

```php
MetaFields::for(Post::class, [
    ColorPicker::make('accent')->label(__('Accent colour')),   // inferred: matches()
    TextInput::make('border')->metaType('color'),              // chosen
    'background' => 'color',                                   // by type: field()
]);

$post->setMeta('accent', 'ff0000');
$post->getMeta('accent');                  // Color('#FF0000')
Post::whereMeta('accent', '#ff0000');      // serialized before comparing
Post::orderByMeta('accent');
```

# Columns

| `MetaColumn` | Column | Indexed |
| --- | --- | --- |
| `String` | `value_string`, varchar(255) | yes |
| `Text` | `value_text`, long text | no |
| `Integer` | `value_int`, bigint | yes |
| `Float` | `value_float`, double | yes |
| `DateTime` | `value_datetime`, datetime | yes |
| `Json` | `value_json`, JSON | no |

Choose an indexed column for anything you filter or sort by.

# Queries

By default, `whereMeta($key, $operator, $value)` serializes the value and compares it with the type's column, and `whereMetaIn` does the same for each value. Override `where()` and `whereIn()` to query differently. They get the query on the meta table, already constrained to the model and key. This is how the built-in `json` type turns `=` into "contains":

```php
use Illuminate\Database\Query\Builder;

public function where(Builder $query, string $operator, mixed $value, string $key = ''): void
{
    if (! in_array($operator, ['=', '!=', '<>'], true)) {
        throw MetaException::unsupportedOperator($this->name(), $operator);
    }

    $query->whereJsonContains($this->column()->value, $value, 'and', $operator !== '=');
}
```

# Matching order

The types are asked to match a field (`matches()`) or a value (`accepts()`) in reverse registration order, so your types are asked before the built-in ones. If none matches, the type is `string`.

Registering a type with the name of an existing one replaces it. For example, this allows longer indexed strings when your column is longer:

```php
class LongStringType extends \Javaabu\Meta\Types\StringType
{
    public const int MAX_LENGTH = 500;
}

MetaFields::type(LongStringType::class);
```

# Built-in types

`string`, `text`, `integer`, `float`, `boolean`, `date`, `datetime`, `time` and `json` are in `Javaabu\Meta\Types`. Extend them to change one detail.
