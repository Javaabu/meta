---
title: Installation & Setup
sidebar_position: 1.2
---

# Installing

Meta and its Javaabu dependencies aren't on Packagist yet. Add their repositories, then require the package:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/Javaabu/meta" },
    { "type": "vcs", "url": "https://github.com/Javaabu/stencil" },
    { "type": "vcs", "url": "https://github.com/Javaabu/forms" },
    { "type": "vcs", "url": "https://github.com/Javaabu/manifest" }
]
```

```bash
composer require javaabu/meta:^1.0@dev
php artisan migrate
```

The service provider registers itself. Its migration runs with your own and creates the `meta` table. There's nothing else to configure.

# Adding meta to a model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;

class Post extends Model
{
    use HasMeta;

    protected $fillable = ['title', 'body'];
}
```

`HasMeta` adds `meta` to `$fillable`, so `Post::create(['title' => …, 'meta' => [...]])` works. See [HasMeta](./basic-usage/has-meta.md).

# UUID or ULID keys

If your models' keys are UUIDs or ULIDs, set `meta.morph_key` before you migrate. Publish the config to change it:

```bash
php artisan vendor:publish --tag=meta-config
```

```php
// config/meta.php
'morph_key' => 'uuid',
```

# Publishing

Everything has a default, so publish only what you change:

```bash
php artisan vendor:publish --tag=meta-config         # config/meta.php
php artisan vendor:publish --tag=meta-migrations     # the migration, to edit it (then set meta.run_migrations to false)
php artisan vendor:publish --tag=meta-translations   # lang/vendor/meta
```
