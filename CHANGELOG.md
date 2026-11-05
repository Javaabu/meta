# Changelog

All notable changes to `javaabu/meta` are documented in this file.

## Unreleased

Initial development of v1.0:

- `HasMeta`: a `meta` relation whose collection reads values by key, `getMeta`, `setMeta`, `hasMeta`, `removeMeta`, `syncMeta`, `getAllMeta` and `fillMeta`, mass assignment of defined keys with `'meta' => [...]`, meta for unsaved records written on save, and meta deleted with the record (kept on soft delete).
- Storage: a shared table with typed value columns (`value_string`, `value_text`, `value_int`, `value_float`, `value_datetime`, `value_json`) and per-column indexes, chosen with benchmarks on MySQL and SQLite (`benchmarks/`). Models can use a table of their own (`$meta_table`, `MetaTable::columns()`), and the migration supports UUID or ULID keys.
- Definitions with javaabu/stencil fields: `MetaFields::for()`, groups of fields per record (`metaGroup()`), closures, types inferred from the field or set with `->metaType()`, and `key => type` definitions.
- Meta types: string, text, integer, float, boolean, date, datetime, time and json, plus custom types (`MetaType`, `MetaFields::type()`, `config('meta.types')`).
- Query scopes: `whereMeta`, `orWhereMeta`, `whereMetaIn`, `orWhereMetaIn`, `whereMetaNotIn`, `orWhereMetaNotIn`, `whereHasMeta`, `orWhereHasMeta`, `whereDoesntHaveMeta`, `orWhereDoesntHaveMeta`, `orderByMeta`, `orderByMetaDesc` and `withMeta`. They're index-only subqueries, and EXPLAIN tests run on SQLite and MySQL.
- `MetaForm`: a stencil form of a record's meta fields, for Blade and Inertia React, with field and type validation and saving.
- Meta fields in the record's own stencil form: `MetaFields::nested()` (a `Group` with `statePath('meta')`) and `MetaFields::formFields()`. Translatable meta fields get per-locale inputs there through stencil's `ResolvesTranslatableFields`, which `HasMeta::isTranslatableField()` implements. `MetaFields::isTranslatable()` tells whether a key is translatable.
- `TextInput::integer()` fields are stored as integers without `->metaType('integer')` (stencil's `isInteger()`), and translatable fields are detected with stencil's `isDeclaredTranslatable()`. Needs stencil from Javaabu/stencil#16. Rendering a form now uses `toHtml()` or `{{ $form }}`, since stencil forms aren't `Stringable` any more.
- Optional javaabu/translatable support: per-locale values with fallback, one input per locale in meta forms, queries in any locale and sorting in the current one.
- English and Dhivehi strings, and Thaana inputs.
- Docs, `llms.txt`, and a Laravel Boost guideline and skill.
