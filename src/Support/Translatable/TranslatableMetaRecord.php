<?php

namespace Javaabu\Meta\Support\Translatable;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Support\Translations;
use Javaabu\Translatable\Contracts\JsonTranslatable;
use Javaabu\Translatable\JsonTranslatable\IsJsonTranslatable;

/**
 * What a meta form edits when it has translatable fields and javaabu/translatable
 * is enabled: a model that is never saved, holding each value in the default
 * locale and the other locales in `translations`. Stencil then renders a
 * translatable field as one input per locale (subtitle_en, subtitle_dv).
 *
 * Only loaded when javaabu/translatable is installed.
 */
class TranslatableMetaRecord extends Model implements JsonTranslatable
{
    use IsJsonTranslatable;

    public $timestamps = false;

    protected $guarded = [];

    /** @var array<int, string> */
    protected array $translatable = [];

    /**
     * @param  array<string, mixed>  $values  key => value; translatable keys as locale => value ('' for a value without a locale)
     * @param  array<int, string>  $translatable_keys
     */
    public static function fromValues(array $values, array $translatable_keys): static
    {
        $record = new static;
        $record->translatable = array_values($translatable_keys);

        $default = Translations::defaultLocale();
        $attributes = ['lang' => $default];
        $translations = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, $record->translatable, true)) {
                $attributes[$key] = $value;

                continue;
            }

            $locales = is_array($value) ? $value : ['' => $value];

            $attributes[$key] = $locales[$default] ?? $locales[''] ?? null;

            foreach ($locales as $locale => $translation) {
                if ($locale !== '' && $locale !== $default) {
                    $translations[$locale][$key] = $translation;
                }
            }
        }

        $attributes['translations'] = json_encode($translations, JSON_UNESCAPED_UNICODE);

        $record->setRawAttributes($attributes);

        return $record;
    }

    /**
     * @return array<int, string>
     */
    public function getTranslatables(): array
    {
        return $this->translatable;
    }

    /**
     * Values are kept in the default locale.
     */
    public function primaryLocale(): string
    {
        return $this->getAttributeValue('lang') ?: Translations::defaultLocale();
    }

    public function getFieldsIgnoredForTranslation(): array
    {
        return ['id', 'translations', 'lang'];
    }
}
