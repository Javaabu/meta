---
title: Introduction
sidebar_position: 1.0
---

# Meta

Meta fields for Eloquent models: values you want to keep on a model without adding a column for each one, such as a post's subtitle, an event's date or a product's weight.

- **Defining a field.** Each field is a [javaabu/stencil](https://github.com/Javaabu/stencil) field, so one definition gives the storage type, the validation rules and the form input.
- **Storage.** Values are stored in typed, indexed columns, so filtering and sorting by meta stays fast.
- **Querying.** `whereMeta`, `whereMetaIn` and `orderByMeta` filter and sort by meta, and eager loading avoids N+1 queries.

```php
use App\Models\Post;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\TextInput;

// AppServiceProvider::boot()
MetaFields::for(Post::class, [
    TextInput::make('subtitle')->label(__('Subtitle'))->maxLength(255)->thaana(),
    Checkbox::make('featured')->label(__('Featured')),
    TextInput::make('views')->integer(),
    DatePicker::make('event_date')->label(__('Event date')),
]);
```

```php
use Javaabu\Meta\Concerns\HasMeta;

class Post extends Model
{
    use HasMeta;
}
```

```php
$post->setMeta(['subtitle' => 'ރާއްޖޭގެ ޚަބަރު', 'featured' => true]);
$post->getMeta('subtitle');                      // 'ރާއްޖޭގެ ޚަބަރު'

Post::create(['title' => 'ސުރުހީ', 'meta' => ['featured' => true, 'views' => 0]]);

Post::whereMeta('featured', true)
    ->whereMeta('event_date', '>=', today())
    ->orderByMeta('views', 'desc')
    ->with('meta')
    ->paginate();
```

```php
// A form for the meta fields, rendered by stencil in Blade or React
$form = MetaForm::for($post)->action(route('posts.meta.update', $post));

MetaForm::for($post)->save();   // validates the request and saves the meta
```

# What's in the box

- **[`HasMeta`](./basic-usage/has-meta.md):** a `meta` relation, reading and writing values, and mass assignment with the model.
- **[Defining meta fields](./basic-usage/defining-fields.md)** with stencil fields:
  - the meta type is inferred from the field (checkboxes are booleans, date pickers are dates)
  - groups give some records extra fields, such as post types
- **[Reading and writing](./basic-usage/reading-and-writing.md):** typed values (Carbon dates, booleans, integers, arrays), with null to remove a value.
- **[Query scopes](./basic-usage/querying.md):** `whereMeta`, `orWhereMeta`, `whereMetaIn`, `whereMetaNotIn`, `whereHasMeta`, `whereDoesntHaveMeta`, `orderByMeta` and `withMeta`.
- **[Meta forms](./basic-usage/forms.md):** a stencil form of a record's meta fields for Blade or Inertia React, with validation and saving.
- **[Custom meta types](./advanced-usage/custom-types.md):** your own storage column, casting, querying and stencil field.
- **[Translatable meta](./advanced-usage/translatable.md)** with [javaabu/translatable](https://github.com/Javaabu/translatable): a value per language and one input per language. Translatable is optional.
- **[Storage design](./advanced-usage/storage-and-performance.md):** benchmarks on MySQL and SQLite, and per-model meta tables.
- English and Dhivehi strings, and Thaana inputs.
