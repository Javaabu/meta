# meta: notes for AI coding agents

`javaabu/meta` is a Laravel package for meta fields on Eloquent models.
- **Definitions:** meta fields are defined with javaabu/stencil fields per model and group (`MetaFields::for()`).
- **Storage:** values go in a typed-columns meta table with indexes per value column.
- **Model side:** `HasMeta` adds reading, writing, mass assignment and query scopes.
- **Forms:** `MetaForm` is a stencil form of a record's meta fields.
- **Translatable:** javaabu/translatable is optional and adds per-locale values.

## Layout

| Path | What |
| --- | --- |
| `src/Concerns/HasMeta.php` | The trait: relation, reads, writes (upsert), pending meta, mass assignment, scopes |
| `src/MetaFields.php` | The registry: definitions by model/group, type registration and inference, `nested()` fields for the record's own form |
| `src/Query/MetaQuery.php` | The SQL of the scopes: `id IN (subquery)` conditions, left-join sorts |
| `src/Types/` | `MetaType` and the built-in types |
| `src/Models/Meta.php`, `MetaCollection.php` | A row; the rows of a record, readable by key (`$post->meta['subtitle']`, `data_get`) |
| `src/Forms/MetaForm.php` | The stencil form |
| `src/Support/Translations.php` | The only place that checks for javaabu/translatable |
| `src/Support/Translatable/TranslatableMetaRecord.php` | The never-saved JSON-translatable record a form with translatable fields binds |
| `src/Support/MetaTable.php`, `database/migrations` | The table's columns and indexes |
| `benchmarks/` | The storage benchmark and its results |
| `tests/` | `Unit`, `Feature` and `Translatable` suites; fixtures and their migration in `tests/Fixtures`, `tests/database` |
| `docs`, `llms.txt`, `resources/boost` | Docs, the index for assistants, the Laravel Boost guideline and skill |

## Rules

- **Codestyle:** follow the Javaabu PHP guidelines (`vendor/javaabu/boost-javaabu-guidelines`): snake_case variables and properties, camelCase methods, `#[Test]` methods named `it_…`, early returns, no `else`.
- **Indexes:**
  - A condition must stay `model.id IN (select metable_id from meta where metable_type = ? and key = ? and value_* …)`, so the `(metable_type, key, value_*, metable_id)` indexes cover it.
  - Don't add a `locale` condition to where-scopes, because it isn't in those indexes.
  - `QueryScopesTest` checks `EXPLAIN` on SQLite and MySQL. Run both (`DB_CONNECTION=mysql vendor/bin/phpunit` against a local MySQL with a `meta_test` database).
- **Translatable is optional:**
  - Only `Support\Translations` and `Support\Translatable\*` reference its classes, and the latter load only when it's enabled.
  - Tests that need it go in `tests/Translatable`. CI runs `Unit,Feature` without it.
- **Stencil:** use only its public API. Requests for more go to Javaabu/stencil as `cross-package` issues (Javaabu/stencil#16 added `isInteger()`, `isDeclaredTranslatable()`, `ResolvesTranslatableFields` and `statePath()`).
- **Nested fields:** `MetaFields::STATE_PATH` (`meta`) is both the stencil state path and the mass-assignment attribute. Keep them the same.
- **Every change** needs tests, docs (linked from `llms.txt`), the Boost skill if it changes usage, and a line in `CHANGELOG.md`.
- **Dhivehi:** user-facing strings go in `lang/en` and `lang/dv`, and tests of user-facing behaviour use Thaana.

## Commands

```bash
composer install
composer lint && composer test           # Pint and PHPUnit (SQLite)
DB_CONNECTION=mysql composer test        # MySQL (DB_HOST, DB_DATABASE=meta_test, DB_USERNAME, DB_PASSWORD)
composer benchmark -- --driver=mysql     # the storage benchmark
```
