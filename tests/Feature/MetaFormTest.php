<?php

namespace Javaabu\Meta\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Forms\MetaForm;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;
use PHPUnit\Framework\Attributes\Test;

class MetaFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MetaFields::for(Post::class, [
            TextInput::make('subtitle')->label('ސަބްޓައިޓަލް')->required()->maxLength(100)->thaana(),
            Checkbox::make('featured'),
            TextInput::make('views')->numeric()->metaType('integer'),
            DatePicker::make('event_date'),
            Select::make('tags')->multiple()->options(['news' => 'ޚަބަރު', 'sports' => 'ކުޅިވަރު']),
        ]);
    }

    #[Test]
    public function it_builds_a_stencil_form_of_the_meta_fields_with_their_values(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta(['subtitle' => 'މާލެ', 'featured' => true, 'views' => 3, 'event_date' => '2026-11-05', 'tags' => ['news']]);

        $form = MetaFields::form($post);

        $this->assertInstanceOf(MetaForm::class, $form);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame($post, $form->getMetable());
        $this->assertSame(['subtitle', 'featured', 'views', 'event_date', 'tags'], array_keys($form->getFields()));
        $this->assertSame('މާލެ', $form->getStateValue('subtitle'));
        $this->assertTrue($form->getStateValue('featured'));
        $this->assertSame(3, $form->getStateValue('views'));
        $this->assertSame('2026-11-05', $form->getStateValue('event_date'));
        $this->assertSame(['news'], $form->getStateValue('tags'));
    }

    #[Test]
    public function its_rules_come_from_the_fields_and_their_types(): void
    {
        $rules = MetaForm::for(new Post)->rules();

        $this->assertContains('required', $rules['subtitle']);
        $this->assertContains('max:100', $rules['subtitle']);
        $this->assertContains('max:255', $rules['subtitle']);
        $this->assertContains('integer', $rules['views']);
        $this->assertContains('date', $rules['event_date']);
    }

    #[Test]
    public function saving_validates_and_saves_the_meta(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);

        $saved = MetaForm::for($post)->save(MetaForm::for($post)->validate([
            'subtitle' => 'އައްޑު',
            'featured' => '1',
            'views' => '12',
            'event_date' => '2026-12-01',
            'tags' => ['news', 'sports'],
        ]));

        $this->assertSame($post, $saved);

        $post = Post::find($post->id);

        $this->assertSame('އައްޑު', $post->getMeta('subtitle'));
        $this->assertTrue($post->getMeta('featured'));
        $this->assertSame(12, $post->getMeta('views'));
        $this->assertSame('2026-12-01', $post->getMeta('event_date')->format('Y-m-d'));
        $this->assertSame(['news', 'sports'], $post->getMeta('tags'));
    }

    #[Test]
    public function saving_without_data_validates_the_request(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);

        $this->app['request']->merge(['subtitle' => 'ފުވައްމުލައް', 'views' => '7']);

        MetaForm::for($post)->save();

        $this->assertSame('ފުވައްމުލައް', Post::find($post->id)->getMeta('subtitle'));
    }

    #[Test]
    public function empty_values_remove_the_meta(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta(['subtitle' => 'މާލެ', 'views' => 3]);

        MetaForm::for($post)->save(['subtitle' => 'މާލެ', 'views' => null]);

        $this->assertFalse(Post::find($post->id)->hasMeta('views'));
    }

    #[Test]
    public function invalid_input_fails_with_the_field_labels(): void
    {
        try {
            MetaForm::for(new Post)->validate(['views' => 'many']);

            $this->fail('Validation passed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subtitle', $exception->errors());
            $this->assertArrayHasKey('views', $exception->errors());
            $this->assertStringContainsString('ސަބްޓައިޓަލް', $exception->errors()['subtitle'][0]);
        }
    }

    #[Test]
    public function a_new_record_gets_its_meta_when_it_is_saved(): void
    {
        $post = new Post(['title' => 'ސުރުހީ']);

        MetaForm::for($post)->save(['subtitle' => 'ހުޅުމާލެ']);

        $this->assertTrue($post->hasPendingMeta());

        $post->save();

        $this->assertSame('ހުޅުމާލެ', Post::find($post->id)->getMeta('subtitle'));
    }

    #[Test]
    public function the_form_has_the_fields_of_the_records_group(): void
    {
        MetaFields::for(Post::class, [DatePicker::make('starts_on')], group: 'event');

        $this->assertArrayHasKey('starts_on', MetaForm::for(new Post(['type' => 'event']))->getFields());
        $this->assertArrayNotHasKey('starts_on', MetaForm::for(new Post(['type' => 'news']))->getFields());
        $this->assertArrayHasKey('starts_on', MetaForm::for(new Post, 'event')->getFields());
    }

    #[Test]
    public function it_renders_with_blade(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta('subtitle', 'މާލެ');

        $html = MetaForm::for($post)->action('/posts/1/meta')->toHtml();

        $this->assertStringContainsString('name="subtitle"', $html);
        $this->assertStringContainsString('value="މާލެ"', $html);
        $this->assertStringContainsString('name="featured"', $html);
        $this->assertStringContainsString('name="tags[]"', $html);
        $this->assertStringContainsString('ސަބްޓައިޓަލް', $html);
    }

    #[Test]
    public function it_speaks_dhivehi_and_keeps_thaana_inputs(): void
    {
        app()->setLocale('dv');

        $form = MetaForm::for(new Post)->action('/posts/1/meta');
        $html = $form->toHtml();

        $this->assertSame('ސޭވް ކުރައްވާ', $form->getSubmitLabel());
        $this->assertStringContainsString('ސޭވް ކުރައްވާ', $html);
        $this->assertTrue($form->toArray()['components'][0]['props']['thaana']);

        app()->setLocale('en');

        $this->assertSame('Save', MetaForm::for(new Post)->getSubmitLabel());
    }

    #[Test]
    public function it_serializes_for_inertia_and_react(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta(['subtitle' => 'މާލެ', 'tags' => ['news']]);

        $json = MetaForm::for($post)->toArray();

        $this->assertSame(1, $json['stencil']);
        $this->assertSame('meta-form', $json['id']);
        $this->assertSame(['subtitle' => 'މާލެ', 'featured' => null, 'views' => null, 'event_date' => null, 'tags' => ['news']], $json['state']);
        $this->assertSame(['subtitle', 'featured', 'views', 'event_date', 'tags'], array_map(fn ($node) => $node['key'], $json['components']));
        $this->assertSame('tags[]', $json['components'][4]['name']);
    }

    #[Test]
    public function it_can_be_embedded_in_the_records_own_form(): void
    {
        $html = MetaForm::for(new Post)->embedded()->toHtml();

        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringContainsString('name="subtitle"', $html);
    }
}
