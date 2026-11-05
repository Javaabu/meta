<?php

namespace Javaabu\Meta\Support;

use Javaabu\Translatable\Translatable;

/**
 * Optional javaabu/translatable support, in one place: every call checks the
 * package is installed first.
 */
class Translations
{
    /** Set to false to turn translatable meta off even when the package is installed */
    public static bool $enabled = true;

    /**
     * Whether javaabu/translatable is installed, enabled and has active languages.
     */
    public static function enabled(): bool
    {
        return static::$enabled
            && class_exists(Translatable::class)
            && Translatable::enabled();
    }

    /**
     * The locale values are read and written in.
     */
    public static function currentLocale(): string
    {
        return static::enabled()
            ? Translatable::currentLocale()
            : app()->getLocale();
    }

    /**
     * The locale a translatable value falls back to.
     */
    public static function defaultLocale(): string
    {
        return static::enabled()
            ? Translatable::defaultLocale()
            : (string) config('app.fallback_locale', config('app.locale', 'en'));
    }

    /**
     * The codes of the active locales, the default first.
     *
     * @return array<int, string>
     */
    public static function locales(): array
    {
        if (! static::enabled()) {
            return array_values(array_unique([static::defaultLocale(), app()->getLocale()]));
        }

        return Translatable::locales()->map(fn ($locale) => $locale->code)->values()->all();
    }

    /**
     * Whether a value is a map of locale => value: ['en' => 'Malé', 'dv' => 'މާލެ'].
     */
    public static function isLocaleMap(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || array_is_list($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key) || ! preg_match('/^[a-z]{2,3}([_-][A-Za-z0-9]+)?$/', $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The order locales are read in: the locale, the default, then the value without a locale.
     *
     * @return array<int, string>
     */
    public static function fallbackChain(?string $locale = null): array
    {
        return array_values(array_unique([$locale ?? static::currentLocale(), static::defaultLocale(), '']));
    }
}
