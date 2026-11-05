---
title: Dhivehi and RTL
sidebar_position: 5
---

Meta has no views of its own: meta forms render through stencil with javaabu/forms (Blade) or @javaabu/forms (React), which handle RTL layouts. What meta adds:

- **Strings:** the meta form's submit button is translated (`lang/en`, `lang/dv`). Everything else the user sees comes from your field definitions, so pass labels, help text and options through `__()`.
- **Thaana inputs:** add `->thaana()` to text fields for Dhivehi, as with any stencil field. Translatable fields get the Thaana keyboard on their Dhivehi input by themselves.
- **Dhivehi values:** stored as UTF-8 and compared as stored. Use a `utf8mb4` connection on MySQL (Laravel's default). `whereMeta('subtitle', 'like', 'ރާއް%')` works.
- **Translatable meta:** see [Translatable meta](./translatable.md) for a value per language and one input per language.

# For review

These translations need a Dhivehi speaker to check them:

| Key | English | Dhivehi |
| --- | --- | --- |
| `meta::strings.save` | Save | ސޭވް ކުރައްވާ (the same as stencil's submit button) |
