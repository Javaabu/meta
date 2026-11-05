---
title: Reading and writing
sidebar_position: 3
---

# Reading

```php
$post->getMeta('subtitle');               // the value, or null
$post->getMeta('subtitle', 'ނެތް');       // a default when there's none
$post->hasMeta('subtitle');               // true when it has a value
$post->getAllMeta();                      // ['subtitle' => …, 'featured' => true, …]
```

Values come back cast by their [type](./defining-fields.md#types). Dates are `Carbon` instances, booleans are `bool`, integers are `int` and lists are arrays.

The first read loads all of the record's meta in one query. Later reads use the loaded rows. When you list many records, eager load their meta so each record doesn't make its own query:

```php
$posts = Post::with('meta')->paginate();             // every key
$posts = Post::withMeta(['subtitle', 'featured'])->get();   // only some keys

foreach ($posts as $post) {
    echo $post->getMeta('subtitle');                 // no query
}
```

To load meta every time, add it to the model's `$with`: `protected $with = ['meta'];`.

# Writing

```php
$post->setMeta('subtitle', 'ރާއްޖޭގެ ޚަބަރު');
$post->setMeta(['featured' => true, 'views' => 10, 'event_date' => '2026-12-01']);
```

- **Saved records** are written straight away, in one upsert, without saving the record itself.
- **Records that aren't saved yet** are written when they're saved:

  ```php
  $post = new Post(['title' => 'ސުރުހީ']);
  $post->setMeta('subtitle', 'ސަބްޓައިޓަލް');   // waits
  $post->save();                                  // written now
  ```

- **Values** go through the key's type: `'10'` is stored as `10` for an integer field and `'2026-12-01'` as a date for a date field. A value the type can't store (a word for an integer, or more than 255 characters for a string) throws a `MetaException`.
- **Keys that aren't defined** take the type of the value:

  | Value | Type |
  | --- | --- |
  | `int` | `integer` |
  | `float` | `float` |
  | `bool` | `boolean` |
  | `DateTimeInterface` | `datetime` |
  | arrays and collections | `json` |
  | strings up to 255 characters | `string` |
  | longer strings | `text` |

# Removing

```php
$post->setMeta('subtitle', null);         // null removes a value
$post->removeMeta('subtitle');
$post->removeMeta(['subtitle', 'featured']);
$post->syncMeta(['featured' => true]);    // sets these and removes every other key
```

# Mass assignment and forms

`create()`, `update()` and `fill()` take meta under `meta`. So do stencil forms that save the record (see [Meta forms](./forms.md)). Only [defined keys](./defining-fields.md) are taken.

```php
Post::create(['title' => 'ސުރުހީ', 'meta' => ['subtitle' => 'ސަބްޓައިޓަލް']]);

$post->fillMeta($request->validated());   // defined keys only; translatable inputs (subtitle_dv) too
```

Use `setMeta()` for values your own code sets, and `meta` / `fillMeta()` for values that come from a request.

# Translations

With [translatable meta](../advanced-usage/translatable.md), reads are in the current locale, falling back to the default locale:

```php
$post->getMeta('subtitle');                                // current locale, with fallback
$post->getMeta('subtitle', locale: 'dv');
$post->getMetaTranslation('subtitle', 'dv', fallback: false);
$post->getMetaTranslations('subtitle');                    // ['en' => 'Malé', 'dv' => 'މާލެ']
$post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);
$post->setMeta('subtitle', 'މާލެ', locale: 'dv');
```
