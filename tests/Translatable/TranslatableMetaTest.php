<?php

namespace Javaabu\Meta\Tests\Translatable;

use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Forms\MetaForm;
use Javaabu\Meta\Models\Meta;
use Javaabu\Meta\Support\Translatable\TranslatableMetaRecord;
use Javaabu\Meta\Support\Translations;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Translatable\Facades\Languages;
use Javaabu\Translatable\Testing\InteractsWithLanguages;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('translatable')]
class TranslatableMetaTest extends TestCase
{
    use InteractsWithLanguages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLanguages(['en', 'dv']);

        MetaFields::for(Post::class, [
            TextInput::make('subtitle')->required()->translatable(),
            Textarea::make('summary')->translatable(layout: 'stacked'),
            Checkbox::make('featured')->translatable(),
            TextInput::make('code'),
        ]);
    }

    protected function tearDown(): void
    {
        Translations::$enabled = true;

        parent::tearDown();
    }

    #[Test]
    public function fields_declared_translatable_are_translatable(): void
    {
        $fields = MetaFields::fields(Post::class);

        $this->assertTrue($fields['subtitle']->translatable);
        $this->assertTrue($fields['summary']->translatable);
        $this->assertFalse($fields['code']->translatable);
    }

    #[Test]
    public function translatable_values_are_stored_per_locale(): void
    {
        $post = Post::create(['title' => 'Title']);

        $post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);
        $post->setMeta('code', 'MLE');

        $this->assertEqualsCanonicalizing(['en', 'dv'], Meta::query()->where('key', 'subtitle')->pluck('locale')->all());
        $this->assertSame([''], Meta::query()->where('key', 'code')->pluck('locale')->all());

        $post = Post::find($post->id);

        $this->assertSame('Malé', $post->getMeta('subtitle'));
        $this->assertSame('މާލެ', $post->getMeta('subtitle', locale: 'dv'));
        $this->assertSame(['en' => 'Malé', 'dv' => 'މާލެ'], $post->getMetaTranslations('subtitle'));

        Languages::setCurrentLocale('dv');

        $this->assertSame('މާލެ', $post->getMeta('subtitle'));
        $this->assertSame('MLE', $post->getMeta('code'));
    }

    #[Test]
    public function a_plain_value_is_stored_in_the_current_locale(): void
    {
        Languages::setCurrentLocale('dv');

        $post = Post::create(['title' => 'Title', 'meta' => ['subtitle' => 'ހުޅުމާލެ']]);

        $this->assertSame(['dv'], Meta::query()->pluck('locale')->all());
        $this->assertSame('ހުޅުމާލެ', Post::find($post->id)->getMetaTranslation('subtitle', 'dv', fallback: false));
        $this->assertNull(Post::find($post->id)->getMetaTranslation('subtitle', 'en', fallback: false));
    }

    #[Test]
    public function reading_falls_back_to_the_default_locale_then_the_value_without_a_locale(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta('subtitle', 'Malé', locale: 'en');
        $post->setMeta('summary', 'Before translations', locale: '');

        $post = Post::find($post->id);

        $this->assertSame('Malé', $post->getMetaTranslation('subtitle', 'dv'));
        $this->assertSame('Before translations', $post->getMetaTranslation('summary', 'dv'));
        $this->assertNull($post->getMetaTranslation('subtitle', 'dv', fallback: false));
    }

    #[Test]
    public function per_locale_inputs_can_be_mass_assigned(): void
    {
        $post = Post::create(['title' => 'Title', 'meta' => ['subtitle_en' => 'Malé', 'subtitle_dv' => 'މާލެ', 'code_dv' => 'ignored']]);

        $post = Post::find($post->id);

        $this->assertSame(['en' => 'Malé', 'dv' => 'މާލެ'], $post->getMetaTranslations('subtitle'));
        $this->assertFalse($post->hasMeta('code_dv'));
        $this->assertSame(['subtitle'], array_keys($post->getAllMeta()));
    }

    #[Test]
    public function removing_a_locale_keeps_the_others(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);

        $post->removeMeta('subtitle', 'en');
        $this->assertSame(['dv' => 'މާލެ'], Post::find($post->id)->getMetaTranslations('subtitle'));

        $post->setMeta('subtitle', ['dv' => null]);
        $this->assertFalse(Post::find($post->id)->hasMeta('subtitle'));
    }

    #[Test]
    public function queries_match_any_locale_and_sort_in_the_current_one(): void
    {
        Post::create(['title' => 'A'])->setMeta('subtitle', ['en' => 'b-english', 'dv' => 'ށ']);
        Post::create(['title' => 'B'])->setMeta('subtitle', ['en' => 'a-english']);
        Post::create(['title' => 'C'])->setMeta('subtitle', ['dv' => 'ހ']);

        $this->assertSame(['A'], Post::whereMeta('subtitle', 'ށ')->pluck('title')->all());
        $this->assertSame(['A'], Post::whereMeta('subtitle', 'b-english')->pluck('title')->all());

        $this->assertSame(['C', 'B', 'A'], Post::orderByMeta('subtitle')->pluck('title')->all());

        Languages::setCurrentLocale('dv');

        // ހ (C) and ށ (A) in Dhivehi; B falls back to English.
        $this->assertSame(['B', 'C', 'A'], Post::orderByMeta('subtitle')->pluck('title')->all());
    }

    #[Test]
    public function the_form_has_an_input_per_locale_for_translatable_fields(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta(['subtitle' => ['en' => 'Malé', 'dv' => 'މާލެ'], 'code' => 'MLE']);

        $form = MetaForm::for(Post::find($post->id));

        $this->assertInstanceOf(TranslatableMetaRecord::class, $form->getRecord());
        $this->assertSame('Malé', $form->getStateValue('subtitle_en'));
        $this->assertSame('މާލެ', $form->getStateValue('subtitle_dv'));
        $this->assertSame('MLE', $form->getStateValue('code'));

        $rules = $form->rules();
        $this->assertContains('required', $rules['subtitle_en']);
        $this->assertContains('nullable', $rules['subtitle_dv']);
        $this->assertArrayHasKey('code', $rules);

        $html = (string) $form;
        $this->assertStringContainsString('name="subtitle_en"', $html);
        $this->assertStringContainsString('name="subtitle_dv"', $html);
        $this->assertStringContainsString('value="މާލެ"', $html);

        $node = collect($form->toArray()['components'])->firstWhere('key', 'subtitle');
        $this->assertSame(['subtitle_en', 'subtitle_dv'], array_column($node['translatable']['inputs'], 'name'));
    }

    #[Test]
    public function saving_the_form_saves_every_locale(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);

        MetaForm::for($post)->save(MetaForm::for($post)->validate([
            'subtitle_en' => 'Addu',
            'subtitle_dv' => 'އައްޑު',
            'summary_dv' => 'ކުރު ތަފްސީލް',
            'code' => 'GAN',
        ]));

        $post = Post::find($post->id);

        $this->assertSame(['en' => 'Addu', 'dv' => 'އައްޑު'], $post->getMetaTranslations('subtitle'));
        $this->assertSame(['dv' => 'ކުރު ތަފްސީލް'], $post->getMetaTranslations('summary'));
        $this->assertSame('GAN', $post->getMeta('code'));
    }

    #[Test]
    public function with_translations_off_translatable_fields_are_plain_fields(): void
    {
        Translations::$enabled = false;

        $this->assertFalse(MetaFields::field(Post::class, 'subtitle')->translatable);

        $post = Post::create(['title' => 'Title', 'meta' => ['subtitle' => 'Malé']]);

        $this->assertSame([''], Meta::query()->pluck('locale')->all());
        $this->assertIsArray(MetaForm::for($post)->getRecord());
        $this->assertSame('Malé', MetaForm::for($post)->getStateValue('subtitle'));
    }

    #[Test]
    public function values_saved_per_locale_are_still_read_with_translations_off(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta('subtitle', ['en' => 'Malé', 'dv' => 'މާލެ']);

        $this->withoutTranslatable(function () use ($post) {
            app()->setLocale('dv');

            $this->assertSame('މާލެ', Post::find($post->id)->getMeta('subtitle'));
        });
    }
}
