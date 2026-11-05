---
title: Adding meta to a model
sidebar_position: 1
---

Add the `HasMeta` trait to any Eloquent model:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;

class Post extends Model
{
    use HasMeta;

    protected $fillable = ['title', 'type'];
}
```

The model gets:

| | |
| --- | --- |
| `meta()` | A `MorphMany` relation to its meta rows. `Post::with('meta')` eager loads them |
| `getMeta()`, `setMeta()`, `hasMeta()`, `removeMeta()`, `syncMeta()`, `getAllMeta()`, `fillMeta()` | [Reading and writing](./reading-and-writing.md) |
| `meta` attribute | Mass assignment: `Post::create(['title' => …, 'meta' => [...]])` |
| `whereMeta()`, `orderByMeta()`, … | [Query scopes](./querying.md) |
| `metaFields()`, `metaField($key)` | The record's [defined fields](./defining-fields.md) |
| `metaGroup()` | The record's group of extra fields, `null` by default ([groups](./defining-fields.md#groups)) |

# Mass assignment

`HasMeta` adds `meta` to `$fillable`. Models with `$guarded` work too.

```php
$post = Post::create([
    'title' => 'ރާއްޖޭގެ ޚަބަރު',
    'meta' => ['subtitle' => 'ސަބްޓައިޓަލް', 'featured' => true],
]);

$post->update(['meta' => ['views' => 10]]);
```

Mass assigned meta is written after the record is saved, and only for [defined keys](./defining-fields.md). Other keys are dropped, like attributes that aren't fillable. With `Model::preventSilentlyDiscardingAttributes()`, they throw instead. To write any key, use `setMeta()`.

# Deleting

Deleting a record deletes its meta. A soft-deleted record keeps its meta until it's force deleted.

# The meta relation

`$post->meta` is a `Javaabu\Meta\Models\MetaCollection` of `Javaabu\Meta\Models\Meta` rows. A string offset reads a value by key, in the current locale:

```php
$post->meta['subtitle'];            // the value, cast by its type
isset($post->meta['subtitle']);
data_get($post, 'meta.subtitle');   // the same
```

Integer offsets are rows, as in any collection. Prefer `getMeta()` in your own code.

# A table per model

All models share the `meta` table by default. To keep a model's meta in a table of its own, set `$meta_table` and create the table with `MetaTable::columns()`. Either way, the queries are the same. See [Storage and performance](../advanced-usage/storage-and-performance.md) for when it's worth it.

```php
class Product extends Model
{
    use HasMeta;

    protected string $meta_table = 'product_meta';
}
```

```php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Javaabu\Meta\Support\MetaTable;

Schema::create('product_meta', function (Blueprint $table) {
    MetaTable::columns($table, 'product_meta'); // 'uuid' or 'ulid' as a third argument for those keys
});
```
