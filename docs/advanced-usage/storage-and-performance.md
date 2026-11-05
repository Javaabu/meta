---
title: Storage and performance
sidebar_position: 3
---

# The meta table

Every value is one row:

| Column | |
| --- | --- |
| `metable_type`, `metable_id` | The record (its morph class and key) |
| `key` | The meta key |
| `locale` | The locale of a [translatable](./translatable.md) value, `''` otherwise |
| `type` | The meta type, used to read the value back |
| `value_string`, `value_text`, `value_int`, `value_float`, `value_datetime`, `value_json` | The value, in its type's column; the others are null |
| `created_at`, `updated_at` | |

| Index | Used by |
| --- | --- |
| unique `(metable_type, metable_id, key, locale)` | Reading and eager loading a record's meta, upserts, `orderByMeta` joins |
| `(metable_type, key, value_string, metable_id)` and the same for `value_int`, `value_float` and `value_datetime` | `whereMeta`, `whereMetaIn` and the other conditions |

A condition is a subquery that the value index answers without reading a row:

```sql
select * from posts
where posts.id in (
    select meta.metable_id from meta
    where meta.metable_type = 'App\Models\Post' and meta.key = 'views' and value_int > 100
)
```

The test suite checks the plans (`EXPLAIN`) on SQLite and MySQL, so a change that stops using these indexes fails CI.

# Why this design

There were three candidates:
- **A:** this typed-columns table.
- **B:** a JSON column on the model's own table, with or without generated, indexed columns per key.
- **C:** a typed table per model.

I benchmarked them with `benchmarks/storage.php`, using 100,000 posts with 8 meta keys each. That's 800,000 rows, and the shared table also holds 400,000 rows of another model. Times are the median of 7 runs, in milliseconds. The full results are in `benchmarks/RESULTS.md`.

| Query | A, MySQL 9.7 | A, SQLite 3.45 | C, MySQL | JSON, MySQL | JSON, SQLite | JSON + generated columns, MySQL | JSON + generated columns, SQLite |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| string `=`, selective | 0.50 | 0.02 | 0.31 | 0.20 | 35 | 0.18 | 0.01 |
| boolean `=` matching 10%, latest 20 | 24 | 0.80 | 22 | 0.33 | 0.10 | 0.17 | 0.04 |
| string `IN` matching 20%, count | 25 | 5.8 | 21 | 24 | 35 | 3.0 | 0.39 |
| integer range matching 1%, sorted by it | 5.9 | 0.96 | 5.1 | 63 | 38 | 0.20 | 0.04 |
| sort the whole table, no filter | 382 | 58 | 310 | 0.28 | 39 | 0.19 | 0.04 |
| boolean and string, sorted by an integer | 88 | 6.4 | 53 | 13 | 38 | 1.8 | 7.0 |
| datetime range matching 8%, count | 10 | 4.9 | 9.4 | 8.7 | 44 | 0.93 | 0.54 |
| eager load the meta of 20 posts | 0.98 | 0.35 | 0.53 | 0.22 | 0.03 | 0.18 | 0.03 |

- **JSON with generated columns is the fastest, but it needs a migration on the model's table for every key you query.** That doesn't fit fields defined in code, groups of fields per post type, custom types, or package models.
- **JSON without generated columns loses to the typed table on most filters.**
- **The typed table answers selective conditions in under a millisecond on both databases.** Conditions that match 10–20% of a large table take 5–25 ms.
- **`id IN (subquery)` beats `EXISTS`.** On SQLite it's 0.02 ms against 43 ms for a selective filter. On MySQL they're the same. The scopes use `IN`.
- **A table per model is 10–40% faster and half the size, because each row belongs to one model.** It's an option (below).

## The weak spot

Sorting a large table by meta without a selective filter (`Post::orderByMeta('views')->paginate()` over every post) reads the whole table. It took about 380 ms on MySQL with 100,000 posts. Filters that match a large share of rows, combined with a sort, are slower too. For a key you sort large tables by on every page load, make it a real column (or a generated column) and keep meta for the long tail of fields.

# A table per model

```php
class Product extends Model
{
    use HasMeta;

    protected string $meta_table = 'product_meta';
}
```

```php
Schema::create('product_meta', fn (Blueprint $table) => MetaTable::columns($table, 'product_meta'));
```

It has the same columns and indexes, `metable_type` included. Everything works the same.

# Running the benchmark

```bash
php benchmarks/storage.php --driver=sqlite --posts=100000
php benchmarks/storage.php --driver=mysql --posts=100000 --host=127.0.0.1 --database=meta_bench --username=root
```

`--reuse` runs the queries again without re-seeding.
