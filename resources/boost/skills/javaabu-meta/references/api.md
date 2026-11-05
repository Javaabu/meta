# javaabu/meta API

## HasMeta (on the model)

| Method | |
| --- | --- |
| `meta()` | `MorphMany` of `Javaabu\Meta\Models\Meta`; `$post->meta['key']` reads a value |
| `getMeta($key, $default = null, ?$locale = null)` | Cast value; current locale with fallback |
| `getMetaTranslation($key, $locale, $fallback = true)`, `getMetaTranslations($key)` | Per locale |
| `hasMeta($key)`, `getAllMeta()` | |
| `setMeta($key\|array, $value = null, ?$locale = null)` | Writes now (on save for new records); null removes |
| `removeMeta($keys, ?$locale = null)`, `syncMeta(array)` | |
| `fillMeta(array $validated)` | Defined keys and `{key}_{locale}` inputs only |
| `'meta' => [...]` in `create()`/`update()`/`fill()` | The same, written on save |
| `metaFields()`, `metaField($key)`, `metaGroup()` | Definitions; override `metaGroup()` |
| `$meta_table` property | A table of its own (create it with `MetaTable::columns($table, 'post_meta')`) |

## Scopes

`whereMeta($key, $op = null, $value = null)`, `orWhereMeta`, `whereMetaIn($key, $values)`, `orWhereMetaIn`, `whereMetaNotIn`, `orWhereMetaNotIn`, `whereHasMeta($keys)`, `orWhereHasMeta`, `whereDoesntHaveMeta($keys)`, `orWhereDoesntHaveMeta`, `orderByMeta($key, $dir = 'asc', ?$type = null)`, `orderByMetaDesc($key, ?$type)`, `withMeta(?array $keys)`.

- Operators: `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like`, `not like`.
- A `null` value means "doesn't have the key" with `=`, and "has it" with `!=`.
- `!=` and `NotIn` match only records that have the key.
- JSON values: `=` is "contains", `In` is "contains any".
- Translatable keys match in any locale and sort in the current locale.
- Undefined keys compare in the column of the value you pass, and need a `$type` to sort.

## MetaFields facade

`for($model, array|Closure $fields, ?$group = null)`, `forget(?$model, ?$group)`, `fields($model|$record, ?$group)`, `field($model, $key, ?$group)`, `keys(...)`, `groups($model)`, `queryField($model, $key)`, `isTranslatable($model|$record, $key, ?$group)`, `form($record, ?$group)`, `formFields($model|$record, ?$group)` (fresh stencil fields), `nested($model|$record, ?$group)` (a `Group` with `statePath('meta')`), `type(MetaType|class)`, `getType($name)`, `hasType`, `types()`, `fieldType(Field)`, `typeForValue($value)`.

Stencil fields get `->metaType('float')` to override the inferred type. `HasMeta::isTranslatableField($state_path)` implements stencil's `ResolvesTranslatableFields` for models that declare it.

`MetaField` (readonly): `key`, `type`, `field` (a fresh stencil copy), `translatable`, `group`.

## MetaForm (a stencil Form)

`MetaForm::for($record, ?$group = null, ?$id = 'meta-form')`, `->action()`, `->method()`, `->embedded()`, `->rules()`, `->validate($request)`, `->save(?$data)` (returns the record), `->toArray()` (Inertia), `{{ $form }}` or `->toHtml()` (Blade).

- Inputs are named after the keys. Translatable fields have one input per locale: `subtitle_en`, `subtitle_dv`.
- Live fields aren't supported. Conditions are.

## Config (`config/meta.php`)

`table` (`meta`), `model`, `morph_key` (`id`, `uuid` or `ulid`, before migrating), `run_migrations`, `types` (custom type classes).
