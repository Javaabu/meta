{{--
    javaabu/meta guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

## javaabu/meta

- Custom fields on Eloquent models are meta fields with `javaabu/meta`:
  - `use HasMeta` on the model.
  - Define the fields with stencil fields in a service provider: `MetaFields::for(Post::class, [TextInput::make('subtitle'), Checkbox::make('featured')])`. Use `group:` with `metaGroup()` for per-type fields.
  - Don't add columns, JSON attributes or hand-made key/value tables for them.
- Read with `$post->getMeta('subtitle')`, and eager load lists with `Post::with('meta')`. Write with `$post->setMeta([...])`, which saves straight away. Request data goes through `'meta' => [...]` in `create()`/`update()`, `fillMeta()` or `MetaForm::for($post)->save()`, which only take defined keys.
- Query with the scopes (`whereMeta`, `whereMetaIn`, `whereMetaNotIn`, `whereHasMeta`, `whereDoesntHaveMeta`, `orderByMeta`), never with joins on the meta table. Integer inputs need `->metaType('integer')`.
- Follow Javaabu codestyle: import every class, pass every label and option through `__()`, and add `->thaana()` to text fields for Dhivehi.
- Always activate the `javaabu-meta` skill when adding meta or custom fields to a model, querying or sorting by meta, building a meta form, writing a meta type, or translating meta values.
