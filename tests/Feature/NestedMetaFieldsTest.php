<?php

namespace Javaabu\Meta\Tests\Feature;

use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\Fixtures\PostForm;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Components\Group;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;
use PHPUnit\Framework\Attributes\Test;

class NestedMetaFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MetaFields::for(Post::class, [
            TextInput::make('subtitle')->label('ސަބްޓައިޓަލް')->required()->thaana(),
            Checkbox::make('featured'),
            TextInput::make('views')->integer(),
        ]);

        MetaFields::for(Post::class, [TextInput::make('venue')], group: 'event');
    }

    protected function postForm(Post $post): Form
    {
        return Form::make()->model($post)->schema([
            TextInput::make('title')->required(),
            MetaFields::nested($post),
        ]);
    }

    #[Test]
    public function it_nests_the_meta_fields_under_meta(): void
    {
        $group = MetaFields::nested(Post::class);

        $this->assertInstanceOf(Group::class, $group);

        $form = $this->postForm(new Post);

        $this->assertSame(['title', 'meta.subtitle', 'meta.featured', 'meta.views'], array_keys($form->getFields()));
        $this->assertSame('meta[subtitle]', $form->getField('meta.subtitle')->getInputName());
        $this->assertSame('meta-subtitle', $form->getField('meta.subtitle')->getId());
    }

    #[Test]
    public function it_gives_new_copies_of_the_fields_each_time(): void
    {
        $first = MetaFields::formFields(Post::class);
        $second = MetaFields::formFields(Post::class);

        $this->assertSame(['subtitle', 'featured', 'views'], array_map(fn ($field) => $field->getName(), $first));
        $this->assertNotSame($first[0], $second[0]);
    }

    #[Test]
    public function it_takes_the_records_group(): void
    {
        $form = $this->postForm(new Post(['type' => 'event']));

        $this->assertNotNull($form->getField('meta.venue'));
    }

    #[Test]
    public function it_is_filled_from_the_records_meta(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta(['subtitle' => 'މާލެ', 'featured' => true, 'views' => 3]);

        $form = $this->postForm(Post::find($post->id));

        $this->assertSame('ސުރުހީ', $form->getStateValue('title'));
        $this->assertSame('މާލެ', $form->getStateValue('meta.subtitle'));
        $this->assertTrue($form->getStateValue('meta.featured'));
        $this->assertSame(3, $form->getStateValue('meta.views'));
    }

    #[Test]
    public function its_rules_are_keyed_by_the_nested_path(): void
    {
        $rules = $this->postForm(new Post)->rules();

        $this->assertContains('required', $rules['meta.subtitle']);
        $this->assertContains('max:255', $rules['meta.subtitle']);
        $this->assertContains('integer', $rules['meta.views']);
    }

    #[Test]
    public function saving_the_records_form_saves_its_meta(): void
    {
        $form = $this->postForm(new Post);

        $data = $form->validate(['title' => 'ސުރުހީ', 'meta' => ['subtitle' => 'އައްޑު', 'featured' => '1', 'views' => '12']]);

        $this->assertSame('ސުރުހީ', $data['title']);
        $this->assertSame('އައްޑު', $data['meta']['subtitle']);

        $post = $form->save($data);
        $post = Post::find($post->id);

        $this->assertSame('ސުރުހީ', $post->title);
        $this->assertSame('އައްޑު', $post->getMeta('subtitle'));
        $this->assertTrue($post->getMeta('featured'));
        $this->assertSame(12, $post->getMeta('views'));

        $form = $this->postForm($post);
        $form->save($form->validate(['title' => 'ސުރުހީ', 'meta' => ['subtitle' => 'މާލެ', 'views' => '13']]));

        $post = Post::find($post->id);

        $this->assertSame('މާލެ', $post->getMeta('subtitle'));
        $this->assertSame(13, $post->getMeta('views'));
    }

    #[Test]
    public function it_renders_nested_input_names(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);
        $post->setMeta('subtitle', 'މާލެ');

        $html = $this->postForm(Post::find($post->id))->toHtml();

        $this->assertStringContainsString('name="meta[subtitle]"', $html);
        $this->assertStringContainsString('value="މާލެ"', $html);
        $this->assertStringContainsString('ސަބްޓައިޓަލް', $html);

        $json = $this->postForm(Post::find($post->id))->toArray();

        $this->assertSame('މާލެ', $json['state']['meta']['subtitle']);
    }

    #[Test]
    public function the_record_resolves_only_its_translatable_fields(): void
    {
        $this->assertFalse((new Post)->isTranslatableField('meta.subtitle'));
        $this->assertFalse((new Post)->isTranslatableField('title'));
        $this->assertFalse(MetaFields::isTranslatable(Post::class, 'subtitle'));
    }

    #[Test]
    public function a_form_class_can_nest_the_meta_fields_of_its_record(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ', 'type' => 'event']);
        $post->setMeta(['subtitle' => 'މާލެ', 'venue' => 'ހުޅުމާލެ']);

        $form = PostForm::make()->model(Post::find($post->id));

        $this->assertSame('ހުޅުމާލެ', $form->getStateValue('meta.venue'));

        $form->save($form->validate(['title' => 'ސުރުހީ', 'meta' => ['subtitle' => 'އައްޑު', 'venue' => 'ގަން']]));

        $this->assertSame('ގަން', Post::find($post->id)->getMeta('venue'));
        $this->assertNull(PostForm::make()->model(new Post)->getField('meta.venue'));
    }
}
