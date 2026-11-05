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

class Post extends Model
{
    use HasMeta;

    public function metaGroup(): ?string
    {
        return $this->type;   // the post type's slug
    }
}
```

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

Each definition is a readonly `MetaField` with `key`, `type`, `field`, `translatable` and `group`. Its `field` is a fresh copy of the stencil field, so you can put it in your own forms. Definitions for a parent class apply to its subclasses, so a translatable subclass of your model shares them.

# Forms

`MetaForm::for($post)` is a stencil form of the record's meta fields. Render it on its own, or `->embedded()` inside your own form, with stencil's Blade or React driver. Validate it with `->validate($request)` and save it with `->save($data)`. See [Meta forms](../basic-usage/forms.md).

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
