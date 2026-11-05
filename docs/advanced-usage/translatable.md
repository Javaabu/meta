---
title: Translatable meta
sidebar_position: 2
---

With [javaabu/translatable](https://github.com/Javaabu/translatable) v3 installed, a meta field can have a value per language: a subtitle in English and in Dhivehi.

```bash
composer require javaabu/translatable:^3.0@dev
```

```php
MetaFields::for(Post::class, [
    TextInput::make('subtitle')->label(__('Subtitle'))->required()->translatable(),
    Textarea::make('summary')->label(__('Summary'))->translatable(layout: 'stacked'),
    TextInput::make('code'),   // the same in every language
]);
```

`->translatable()` is always safe to call. The field is translatable when translatable is installed and enabled (`translatable.enabled` is on and there are active languages). Otherwise it's a plain field. The model itself doesn't need to be translatable.

# Storing and reading

A translatable value is stored as one row per locale. A value that isn't translatable has the locale `''`.

```php
$post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);   // a locale map
$post->setMeta('subtitle', 'މާލެ', locale: 'dv');                // one locale
$post->setMeta('subtitle', 'މާލެ');                               // the current locale

$post->getMeta('subtitle');                                  // the current locale, falling back
$post->getMeta('subtitle', locale: 'dv');
$post->getMetaTranslation('subtitle', 'dv', fallback: false);   // null when there's no Dhivehi value
$post->getMetaTranslations('subtitle');                      // ['en' => 'Malé', 'dv' => 'މާލެ']

$post->removeMeta('subtitle', 'dv');   // one locale
$post->removeMeta('subtitle');         // every locale
```

A read falls back in this order:
1. the locale asked for (the current locale by default)
2. the default locale (`Translatable::defaultLocale()`)
3. the value without a locale
4. any locale that has a value

The current locale is translatable's (`Translatable::currentLocale()`), so its locale middleware and language switcher apply.

# Forms

In a [meta form](../basic-usage/forms.md), a translatable field has one input per active language. Each input is named `{key}_{locale}` (`subtitle_en`, `subtitle_dv`), has its language's direction and `lang`, and Dhivehi gets the Thaana keyboard. Stencil renders them in tabs, or stacked with `layout: 'stacked'`, in both Blade and React.

- **Validation:** the default language keeps the field's rules, and the others are optional (`required` becomes `nullable`). Use `->translatable(require_all: true)` to require every language.
- **Saving:** the form saves each language that's submitted, and an empty input removes that language's value.
- **Mass assignment:** `fill(['meta' => ...])` and `fillMeta()` take the same input names, or a locale map under the key.

# Querying

- `whereMeta('subtitle', 'މާލެ')` matches a value in any locale.
- `orderByMeta('subtitle')` sorts by the value in the current locale, falling back to the default locale and then to the value without a locale.

# Without translatable

- **When translatable is missing or off:** before you install it, or with `translatable.enabled` off, a translatable field is a plain field. It has one input and stores values without a locale.
- **Values stored per locale stay readable:** they're read in the app's locale, falling back to `app.fallback_locale`, so turning translations off doesn't break reading.
- **Turning translatable meta off:** set `Javaabu\Meta\Support\Translations::$enabled = false` to turn it off while keeping translatable installed for your models.

# How it works

- **The one place translatable is checked:** `Javaabu\Meta\Support\Translations`. Nothing else in the package references translatable's classes.
- **What the form edits:** a meta form with translatable fields edits a `Javaabu\Meta\Support\Translatable\TranslatableMetaRecord` instead of an array. It's a JSON-translatable model that's never saved, holding the default language's values as attributes and the others in `translations`. That's what stencil and translatable expect, so per-locale inputs, rules and old input work as for any translatable model.
- **When that class loads:** only when translatable is installed.
