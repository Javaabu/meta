---
title: Defining meta fields
sidebar_position: 2
---

Define a model's meta fields once, in a service provider's `boot()`, with [javaabu/stencil](https://github.com/Javaabu/stencil) fields:

```php
use App\Models\Post;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;

public function boot(): void
{
    MetaFields::for(Post::class, [
        TextInput::make('subtitle')->label(__('Subtitle'))->maxLength(255)->thaana(),
        Textarea::make('summary')->label(__('Summary'))->thaana(),
        Checkbox::make('featured')->label(__('Featured')),
        TextInput::make('views')->label(__('Views'))->integer(),
        TextInput::make('rating')->label(__('Rating'))->numeric()->minValue(0)->maxValue(5),
        DatePicker::make('event_date')->label(__('Event date')),
        Select::make('tags')->label(__('Tags'))->multiple()->options([
            'news' => __('News'),
            'sports' => __('Sports'),
        ]),
    ]);
}
```

Each field's name is the meta key. The field gives:

- **The meta type:** which column the value is stored in and how it's cast. It's inferred from the field (below).
- **Validation:** the field's rules (`required`, `maxLength`, options, …) plus the type's rules.
- **The form input** of [meta forms](./forms.md), with its label, help, placeholder, Thaana and conditions.

Defining a field isn't required to store a value: `setMeta()` takes any key. But only defined fields can be mass assigned or edited in a meta form, and queries and sorts know their column.

# Types

| Stencil field | Meta type | Stored in | Read back as |
| --- | --- | --- | --- |
| `TextInput`, single `Select`, `Radio`, `Hidden` | `string` (255 characters at most) | `value_string`, indexed | `string` |
| `Textarea`, `Wysiwyg` | `text` | `value_text` | `string` |
| `TextInput::numeric()` | `float` | `value_float`, indexed | `float` |
| `TextInput::integer()` | `integer` | `value_int`, indexed | `int` |
| `Checkbox` | `boolean` | `value_int` (1 or 0), indexed | `bool` |
| `DatePicker` | `date` | `value_datetime` at midnight, indexed | `Carbon` |
| `DateTimePicker` | `datetime` | `value_datetime`, indexed | `Carbon` |
| `TimePicker` | `time` | `value_string` as `H:i:s`, indexed | `string` |
| `Select::multiple()`, `CheckboxList` | `json` | `value_json` | `array` |

Choose a type yourself with `->metaType()`:

```php
TextInput::make('price')->integer()->metaType('float');
TextInput::make('code')->metaType('text');
```

`TextInput::make('views')->integer()` is stored as an `integer` on its own, and `->numeric()` as a `float`.

Fields that save to a relationship (`Select::relationship()` for a `BelongsToMany`, or a file upload into a media collection) can't be meta fields. Defining one throws a `MetaException`.

# Fields by type

To give a key only a type, use `key => type`. The type provides a plain field for it (a text input, a checkbox, a date picker, …):

```php
MetaFields::for(Post::class, [
    'views' => 'integer',
    'rating' => 'float',
    'featured' => 'boolean',
]);
```

# Groups

A group adds fields to some records of a model only. For example, events and news are both `Post`s, but only events have a start date. Define the group's fields with `group:`, and say which group a record is in with `metaGroup()`:

```php
MetaFields::for(Post::class, [TextInput::make('subtitle')]);                     // every post
MetaFields::for(Post::class, [DatePicker::make('starts_on')], group: 'event');   // events only
MetaFields::for(Post::class, [Checkbox::make('breaking')], group: 'news');       // news only
```

```php
class Post extends Model
{
    use HasMeta;

    public function metaGroup(): ?string
    {
        return $this->type;
    }
}
```

A record's fields are the model's own fields, then its group's. A group can redefine a key of the model's own fields. Queries on the model know every group's fields: `Post::whereMeta('starts_on', '>', today())`. If two groups define the same key with different types, querying that key throws: give it one type.

# Closures

Pass a closure to build the fields when they're needed rather than when the provider boots. Use one when the fields depend on config, the database or the current user:

```php
MetaFields::for(Post::class, fn () => [
    Select::make('category')->options(Category::pluck('name', 'slug')->all()),
]);
```

# Reading definitions

```php
MetaFields::fields(Post::class);           // ['subtitle' => MetaField, …]: the model's own fields
MetaFields::fields($post);                 // the record's fields, with its group's
MetaFields::fields(Post::class, 'event');  // a group's
MetaFields::field($post, 'subtitle');      // ?MetaField
MetaFields::keys($post);                   // ['subtitle', 'starts_on']
MetaFields::groups(Post::class);           // ['event', 'news']
$post->metaFields(); $post->metaField('subtitle');
```

A `Javaabu\Meta\Definitions\MetaField` has these properties:

- `key`
- `type`: a `MetaType`
- `field`: the stencil field. Each definition gets its own copy, so changing it doesn't affect other forms.
- `translatable`
- `group`

Definitions of a parent class apply to its subclasses. Each subclass can add its own.
