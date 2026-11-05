---
title: For package authors
sidebar_position: 6
---

Packages such as javaabu/post-types build on meta: the package owns the model, and apps (or the package itself) define its meta fields. This page is the contract they rely on. It's covered by semver and doesn't change within v1.

# The model

Use `HasMeta` on the package's model, and return the record's group from `metaGroup()` when records of one model have different fields:

```php
namespace Javaabu\PostTypes\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;
use Javaabu\Stencil\Contracts\ResolvesTranslatableFields;

class Post extends Model implements ResolvesTranslatableFields
{
    use HasMeta;

    public function metaGroup(): ?string
    {
        return $this->type;   // the post type's slug
    }
}
```

`ResolvesTranslatableFields` (implemented by `HasMeta::isTranslatableField()`) gives translatable meta fields one input per language in your own forms. It's only needed when you nest meta fields in the record's form.

The meta table comes with meta's own migration, so your package adds none. If your model needs its own table, ship a migration that calls `MetaTable::columns()` and set `$meta_table`.

# Definitions

Register fields for a group from your package's own definition API:

```php
// PostType::make('event')->metaFields([...]) in your package becomes:
MetaFields::for(Post::class, $fields, group: 'event');
```

Read the definitions back with:
- `MetaFields::fields($post)`: a record's fields, with its group's.
- `MetaFields::fields(Post::class, 'event')`: one group's fields.
- `MetaFields::groups(Post::class)`: every group of the model.
- `MetaFields::queryField(Post::class, $key)`: the field queries use for a key.
- `MetaFields::isTranslatable($post, $key)`: whether a key has a value per language.

Each definition is a readonly `MetaField` with `key`, `type`, `field`, `translatable` and `group`. Its `field` is a fresh copy of the stencil field, so you can put it in your own forms. Definitions for a parent class apply to its subclasses, so a translatable subclass of your model shares them.

# Forms

Put the meta fields in your own stencil form for the record with `MetaFields::nested($post)`: a `Group` with `statePath('meta')`. The inputs are `meta[subtitle]`, the values come from `$post->meta`, and `validate()` returns them under `meta`, which `save()`, `create()` and `update()` write when the post is saved:

```php
Form::make()->model($post)->schema([
    TextInput::make('title')->required(),
    Section::make(__('Details'), [MetaFields::nested($post)]),
]);
```

`MetaFields::formFields($post)` gives the bare field copies for a layout of your own. Conditions in definitions take full paths (`meta.featured`) when nested. See [Inside the record's own form](../basic-usage/forms.md#inside-the-records-own-form).

`MetaForm::for($post)` is a stencil form of the record's meta fields alone. Render it on its own, or `->embedded()` inside a form you write yourself, with stencil's Blade or React driver. Validate it with `->validate($request)` and save it with `->save($data)`. See [Meta forms](../basic-usage/forms.md).

# Queries

Your index pages and filters can use the scopes on any `HasMeta` model:
- conditions: `whereMeta`, `orWhereMeta`, `whereMetaIn`, `orWhereMetaIn`, `whereMetaNotIn`, `orWhereMetaNotIn`
- presence: `whereHasMeta`, `orWhereHasMeta`, `whereDoesntHaveMeta`, `orWhereDoesntHaveMeta`
- sorting: `orderByMeta`, `orderByMetaDesc`
- eager loading: `withMeta`

They know every group's fields. For example, a javaabu/filters filter on a meta key:

```php
SelectFilter::make('color')->options($colors)->query(fn (Builder $query, $value) => $query->whereMetaIn('color', (array) $value));
```

# Testing

Definitions are kept in the `MetaFields` singleton. Clear them between tests with `MetaFields::forget()`, or rely on the fresh application each test gets.
