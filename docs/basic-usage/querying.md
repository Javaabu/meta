---
title: Querying
sidebar_position: 4
---

`HasMeta` adds scopes that filter and sort by meta. They use the key's [type](./defining-fields.md#types), so values are compared in the right column: numbers as numbers, dates as dates.

```php
Post::whereMeta('featured', true)
    ->whereMeta('views', '>', 100)
    ->whereMetaIn('color', ['red', 'blue'])
    ->orderByMeta('event_date', 'desc')
    ->with('meta')
    ->paginate();
```

# Conditions

```php
Post::whereMeta('subtitle', 'ރާއްޖެ');                 // =
Post::whereMeta('views', '>=', 100);                  // =, !=, <>, <, >, <=, >=, like, not like
Post::whereMeta('event_date', '>', now());            // dates as Carbon or strings
Post::whereMeta('subtitle', 'like', 'ރާއް%');

Post::whereMeta('color', 'red')->orWhereMeta('featured', true);
Post::where('status', 'published')->where(fn ($query) => $query->whereMeta('color', 'red')->orWhereMeta('color', 'blue'));

Post::whereMetaIn('color', ['red', 'blue']);          // one of the values
Post::whereMetaNotIn('color', ['red']);               // has the key, with a value that isn't one of these
Post::orWhereMetaIn('color', ['green']); Post::orWhereMetaNotIn('color', ['green']);

Post::whereHasMeta('subtitle');                       // has a value
Post::whereHasMeta(['subtitle', 'summary']);          // has every key
Post::whereDoesntHaveMeta('subtitle');                // has none of the keys
Post::orWhereHasMeta('subtitle'); Post::orWhereDoesntHaveMeta('subtitle');

Post::whereMeta('subtitle', null);                    // same as whereDoesntHaveMeta
Post::whereMeta('subtitle', '!=', null);              // same as whereHasMeta
```

- `!=`, `<>` and `whereMetaNotIn` only match records that have the key. To include records without it, add `->orWhereDoesntHaveMeta('color')`.
- **`json` values** (multiple selects, checkbox lists) match lists that contain the value: `whereMeta('tags', 'news')`, `whereMetaIn('tags', ['news', 'sports'])` (contains any), `whereMeta('tags', '!=', 'news')`. Other operators throw.
- **Keys that aren't defined** are compared in the column of the value you pass: `whereMeta('position', '>', 2)` uses the integer column. Pass the same type you stored.
- **Translatable keys** match a value in any locale.

# Sorting

```php
Post::orderByMeta('views');                 // ascending
Post::orderByMeta('views', 'desc');
Post::orderByMetaDesc('views');
Post::orderByMeta('position', 'asc', 'integer');   // a key that isn't defined needs its type
```

- Records without the key come first in ascending order and last in descending order.
- Translatable keys sort by the value in the current locale, falling back to the default locale.
- Sorting adds a left join and selects the model's columns (`posts.*`) when the query has no `select`. Pagination works as usual.
- Sorting by a key that isn't defined, without a type, throws a `MetaException`, because the column isn't known.

# Eager loading

```php
Post::with('meta')->get();
Post::withMeta()->get();                          // the same
Post::withMeta(['subtitle', 'featured'])->get();  // only these keys
```

After `withMeta([...])`, reading another key returns null (its default), not a query.

# Performance

- **Conditions** compile to `posts.id in (select metable_id from meta where metable_type = ? and key = ? and value_int > ?)`. An index on `(metable_type, key, value_*, metable_id)` answers that subquery without reading the meta rows. A selective condition takes under a millisecond on a table with 800,000 rows.
- **Sorting** joins on the unique `(metable_type, metable_id, key, locale)` index.
- **The weak spot** is sorting a large table by meta without a selective filter, which reads the whole table. See [Storage and performance](../advanced-usage/storage-and-performance.md) for the numbers and what to do about it.
