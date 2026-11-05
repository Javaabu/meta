---
title: API reference
sidebar_position: 7
---

# `Javaabu\Meta\Concerns\HasMeta`

| Method | |
| --- | --- |
| `meta(): MorphMany` | The meta rows. Eager load with `with('meta')` |
| `metaRows(): MetaCollection` | The loaded rows, loading them if needed |
| `getMeta(string $key, mixed $default = null, ?string $locale = null): mixed` | A value, cast by its type. In the current locale (or `$locale`), falling back |
| `getMetaTranslation(string $key, string $locale, bool $fallback = true): mixed` | A value in a locale |
| `getMetaTranslations(string $key): array` | `locale => value`, `''` for a value without a locale |
| `hasMeta(string $key): bool` | |
| `getAllMeta(): array` | `key => value` in the current locale |
| `setMeta(string\|array $key, mixed $value = null, ?string $locale = null): static` | Writes straight away (or when an unsaved record is saved). `null` removes a value |
| `removeMeta(string\|array $keys, ?string $locale = null): static` | In every locale, or in one |
| `syncMeta(array $values): static` | Sets these values and removes every other key |
| `fillMeta(array $values): static` | Submitted data: defined keys and `{key}_{locale}` inputs only |
| `setMetaAttribute(?array $value): void` | `meta` in `fill()`, `create()` and `update()`; written on save |
| `hasPendingMeta(): bool` | Meta waiting for the record to be saved |
| `savePendingMeta(): void` | Called when the record is saved |
| `metaFields(): array`, `metaField(string $key): ?MetaField` | The record's definitions |
| `metaGroup(): ?string` | Override to return the record's group |
| `isTranslatableField(string $state_path): bool` | For stencil's `ResolvesTranslatableFields`: `meta.*` paths from the definitions, others from javaabu/translatable |
| `getMetaTable(): string` | `$meta_table`, or `config('meta.table')` |

| Scope | |
| --- | --- |
| `whereMeta($key, $operator = null, $value = null)`, `orWhereMeta(…)` | `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like`, `not like`. `null` checks whether the key is there |
| `whereMetaIn($key, iterable $values)`, `orWhereMetaIn(…)` | |
| `whereMetaNotIn($key, iterable $values)`, `orWhereMetaNotIn(…)` | Has the key, with another value |
| `whereHasMeta(string\|array $keys)`, `orWhereHasMeta(…)` | Has every key |
| `whereDoesntHaveMeta(string\|array $keys)`, `orWhereDoesntHaveMeta(…)` | Has none of the keys |
| `orderByMeta($key, $direction = 'asc', ?string $type = null)`, `orderByMetaDesc($key, ?string $type = null)` | Undefined keys need `$type` |
| `withMeta(?array $keys = null)` | Eager load every key, or some |

# `Javaabu\Meta\Facades\MetaFields` (`Javaabu\Meta\MetaFields`)

| Method | |
| --- | --- |
| `for(string $model, array\|Closure $fields, ?string $group = null)` | Define fields: stencil fields, or `key => type name` |
| `forget(?string $model = null, ?string $group = null)` | Remove definitions |
| `fields(Model\|string $model, ?string $group = null): array<string, MetaField>` | A record's fields with its group's, or a model's (and a group's) |
| `field(Model\|string $model, string $key, ?string $group = null): ?MetaField` | |
| `keys(Model\|string $model, ?string $group = null): array` | |
| `groups(string $model): array` | |
| `queryField(string $model, string $key): ?MetaField` | The field queries use: the model's or any group's |
| `isTranslatable(Model\|string $model, string $key, ?string $group = null): bool` | Whether a key has a value per locale. A model class without a group looks in every group |
| `form(Model $record, ?string $group = null): MetaForm` | |
| `formFields(Model\|string $model, ?string $group = null): array<int, Field>` | New copies of the stencil fields |
| `nested(Model\|string $model, ?string $group = null): Group` | The fields in a stencil `Group` with `statePath('meta')`, for the record's own form |
| `type(MetaType\|string $type)` | Register a type (an instance or a class name) |
| `getType(string $name): MetaType`, `hasType(string $name): bool`, `types(): array` | |
| `fieldType(Field $field): MetaType` | The type of a stencil field: `->metaType()`, or inferred |
| `typeForValue(mixed $value): MetaType` | The type of a value of an undefined key |
| `setFieldType(Field $field, string $type): void` | What `->metaType()` calls |

`MetaFields::STATE_PATH` is `'meta'`. `Field::metaType(string $type)` is a macro on stencil fields.

# `Javaabu\Meta\Definitions\MetaField`

Readonly: `key`, `type` (`MetaType`), `field` (stencil `Field`), `translatable`, `group`. Also `typeRules(): array`.

# `Javaabu\Meta\Forms\MetaForm` (extends `Javaabu\Stencil\Form`)

| Method | |
| --- | --- |
| `MetaForm::for(Model $record, ?string $group = null, ?string $id = null): static` | The id defaults to `meta-form` |
| `metaFields(): array` | The form's definitions |
| `getMetable(): Model` | The record |
| `metaRecord(): array\|TranslatableMetaRecord` | What stencil binds |
| `save(?array $data = null): Model` | Validates the request when `$data` is null, saves the meta, returns the record |

Everything else is stencil's `Form`: `action()`, `method()`, `embedded()`, `rules()`, `validate()`, `toArray()`, `render()`, and so on.

# `Javaabu\Meta\Types\MetaType`

| Method | |
| --- | --- |
| `name(): string` | Abstract |
| `column(): MetaColumn` | Abstract |
| `serialize(mixed $value, string $key = ''): mixed` | To the column. Throw `MetaException::invalidValue()` for values it can't store |
| `unserialize(mixed $value): mixed` | From the column |
| `field(string $key): Field` | The field for `key => type` definitions |
| `matches(Field $field): bool` | For inference |
| `accepts(mixed $value): bool` | For undefined keys |
| `rules(): array` | Added to the field's rules |
| `where(Builder $query, string $operator, mixed $value, string $key = ''): void` | Constrain the meta subquery |
| `whereIn(Builder $query, array $values, bool $not = false, string $key = ''): void` | |

Built in: `StringType` (`MAX_LENGTH = 255`), `TextType`, `IntegerType`, `FloatType`, `BooleanType`, `DateType`, `DateTimeType`, `TimeType` and `JsonType`.

# Others

| Class | |
| --- | --- |
| `Javaabu\Meta\Enums\MetaColumn` | `String`, `Text`, `Integer`, `Float`, `DateTime`, `Json`; `isIndexed()`, `names()` |
| `Javaabu\Meta\Models\Meta` | A row: `getValue()`, `setValue(MetaType $type, mixed $value)`, `getMetaType()`, `metable()` |
| `Javaabu\Meta\Models\MetaCollection` | `findMeta($key, ?$locale, $fallback = true)`, `metaValue($key, $default, ?$locale)`, `hasMeta($key)`, `translations($key)`, `toValues()`; string offsets read values |
| `Javaabu\Meta\Support\MetaTable` | `columns(Blueprint $table, string $name, string $morph_key = 'id')` |
| `Javaabu\Meta\Support\Translations` | `$enabled`, `enabled()`, `currentLocale()`, `defaultLocale()`, `locales()`, `isLocaleMap()`, `fallbackChain()` |
| `Javaabu\Meta\Query\MetaQuery` | What the scopes call: `where()`, `whereIn()`, `whereHas()`, `orderBy()`, `subquery()` |
| `Javaabu\Meta\Exceptions\MetaException` | Unknown types, values a type can't store, ambiguous keys, relationship fields, mass assigning undefined keys |
