---
title: Configuration
sidebar_position: 4
---

Publish the config to change it:

```bash
php artisan vendor:publish --tag=meta-config
```

| Key | Default | What |
| --- | --- | --- |
| `table` | `'meta'` | The table meta is stored in. A model can use its own with `$meta_table` |
| `model` | `Javaabu\Meta\Models\Meta::class` | The model of a meta row. Extend `Javaabu\Meta\Models\Meta` to change it |
| `morph_key` | `'id'` | The type of `metable_id` the migration creates: `id`, `uuid` or `ulid`. Change it before you migrate |
| `run_migrations` | `true` | Run the package's migration with yours. Set it to `false` when you publish the migration (`--tag=meta-migrations`) |
| `types` | `[]` | Your [meta types](./custom-types.md), registered when the app boots |

# Translations

The package has one string: the meta form's submit button ("Save", "ސޭވް ކުރައްވާ"). Publish the strings to change them:

```bash
php artisan vendor:publish --tag=meta-translations   # lang/vendor/meta/{en,dv}/strings.php
```

Field labels are yours: pass them through `__()` in the definitions.
