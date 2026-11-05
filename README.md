# Meta

[![Tests](https://github.com/Javaabu/meta/actions/workflows/tests.yml/badge.svg)](https://github.com/Javaabu/meta/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/Javaabu/meta/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/meta)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/meta.svg)](https://packagist.org/packages/javaabu/meta)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Meta fields for Eloquent models:
- **Defining:** define them once with [javaabu/stencil](https://github.com/Javaabu/stencil) fields.
- **Storing:** values are stored in typed, indexed columns.
- **Querying:** `whereMeta` and `orderByMeta` filter and sort by them, and eager loading avoids N+1s.
- **Editing:** edit them in a form rendered with Blade or Inertia React.
- **Extending:** add your own types.
- **Translating:** translate them with [javaabu/translatable](https://github.com/Javaabu/translatable).

```php
MetaFields::for(Post::class, [
    TextInput::make('subtitle')->maxLength(255)->thaana()->translatable(),
    Checkbox::make('featured'),
    DatePicker::make('event_date'),
]);
```

```php
class Post extends Model
{
    use HasMeta;
}

$post->setMeta(['subtitle' => 'ރާއްޖޭގެ ޚަބަރު', 'featured' => true]);
$post->getMeta('subtitle');

Post::whereMeta('featured', true)->whereMeta('event_date', '>=', today())->orderByMeta('event_date')->with('meta')->paginate();

MetaForm::for($post)->action(route('posts.meta.update', $post));   // {{ $form }} or <StencilForm schema={form} />
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/meta](https://docs.javaabu.com/meta), and in the [`docs`](docs) folder.

AI coding assistants can start from [`llms.txt`](llms.txt). The package ships a [Laravel Boost](https://github.com/laravel/boost) skill and guideline, which Boost finds by itself. See [AI assistants](docs/ai-assistants.md).

## Testing

```bash
composer test                        # SQLite
DB_CONNECTION=mysql composer test    # MySQL
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
