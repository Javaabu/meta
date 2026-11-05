<?php

namespace Javaabu\Meta\Tests\Unit;

use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Tests\Fixtures\Page;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;
use PHPUnit\Framework\Attributes\Test;

class SpecialPost extends Post {}

class MetaFieldsTest extends TestCase
{
    #[Test]
    public function it_defines_fields_for_a_model(): void
    {
        MetaFields::for(Post::class, [TextInput::make('subtitle'), Checkbox::make('featured'), 'views' => 'integer']);

        $fields = MetaFields::fields(Post::class);

        $this->assertSame(['subtitle', 'featured', 'views'], array_keys($fields));
        $this->assertSame('string', $fields['subtitle']->type->name());
        $this->assertSame('boolean', $fields['featured']->type->name());
        $this->assertSame('integer', $fields['views']->type->name());
        $this->assertInstanceOf(TextInput::class, $fields['views']->field);
        $this->assertFalse($fields['subtitle']->translatable);
        $this->assertNull($fields['subtitle']->group);
        $this->assertSame(['max:255'], $fields['subtitle']->typeRules());
        $this->assertSame(['subtitle', 'featured', 'views'], MetaFields::keys(Post::class));
        $this->assertSame([], MetaFields::fields(Page::class));
    }

    #[Test]
    public function each_definition_gets_its_own_copy_of_the_field(): void
    {
        $field = TextInput::make('subtitle');

        MetaFields::for(Post::class, [$field]);

        $first = MetaFields::field(Post::class, 'subtitle')->field;
        $second = MetaFields::field(Post::class, 'subtitle')->field;

        $this->assertNotSame($field, $first);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function closures_are_called_when_the_fields_are_needed(): void
    {
        $calls = 0;

        MetaFields::for(Post::class, function () use (&$calls) {
            $calls++;

            return [TextInput::make('subtitle')];
        });

        $this->assertSame(0, $calls);
        $this->assertSame(['subtitle'], MetaFields::keys(Post::class));
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function later_definitions_of_a_key_replace_earlier_ones(): void
    {
        MetaFields::for(Post::class, [TextInput::make('code')]);
        MetaFields::for(Post::class, ['code' => 'integer']);

        $this->assertSame('integer', MetaFields::field(Post::class, 'code')->type->name());
    }

    #[Test]
    public function groups_add_fields_for_some_records(): void
    {
        MetaFields::for(Post::class, [TextInput::make('subtitle')]);
        MetaFields::for(Post::class, [DatePicker::make('starts_on'), TextInput::make('subtitle')->maxLength(20)], group: 'event');
        MetaFields::for(Post::class, [Checkbox::make('breaking')], group: 'news');

        $this->assertSame(['event', 'news'], MetaFields::groups(Post::class));
        $this->assertSame(['subtitle'], MetaFields::keys(Post::class));
        $this->assertSame(['subtitle', 'starts_on'], MetaFields::keys(Post::class, 'event'));
        $this->assertSame('event', MetaFields::field(Post::class, 'starts_on', 'event')->group);

        $event = new Post(['type' => 'event']);
        $news = new Post(['type' => 'news']);

        $this->assertSame(['subtitle', 'starts_on'], MetaFields::keys($event));
        $this->assertSame(['subtitle', 'breaking'], MetaFields::keys($news));
        $this->assertSame(['subtitle', 'breaking'], array_keys($news->metaFields()));
        $this->assertSame('breaking', $news->metaField('breaking')->key);
        $this->assertNull($event->metaField('breaking'));
    }

    #[Test]
    public function query_fields_come_from_the_model_or_any_group(): void
    {
        MetaFields::for(Post::class, [TextInput::make('subtitle')]);
        MetaFields::for(Post::class, [DatePicker::make('starts_on')], group: 'event');
        MetaFields::for(Post::class, [DatePicker::make('starts_on')], group: 'course');

        $this->assertSame('string', MetaFields::queryField(Post::class, 'subtitle')->type->name());
        $this->assertSame('date', MetaFields::queryField(Post::class, 'starts_on')->type->name());
        $this->assertNull(MetaFields::queryField(Post::class, 'missing'));
    }

    #[Test]
    public function definitions_of_a_parent_class_apply_to_subclasses(): void
    {
        MetaFields::for(Post::class, [TextInput::make('subtitle')]);
        MetaFields::for(SpecialPost::class, [Checkbox::make('special')]);

        $this->assertSame(['subtitle', 'special'], MetaFields::keys(SpecialPost::class));
        $this->assertSame(['subtitle'], MetaFields::keys(Post::class));
    }

    #[Test]
    public function definitions_can_be_forgotten(): void
    {
        MetaFields::for(Post::class, [TextInput::make('subtitle')]);
        MetaFields::for(Post::class, [Checkbox::make('breaking')], group: 'news');
        MetaFields::for(Page::class, [TextInput::make('heading')]);

        MetaFields::forget(Post::class, 'news');
        $this->assertSame([], MetaFields::groups(Post::class));

        MetaFields::forget(Post::class);
        $this->assertSame([], MetaFields::keys(Post::class));
        $this->assertSame(['heading'], MetaFields::keys(Page::class));

        MetaFields::forget();
        $this->assertSame([], MetaFields::keys(Page::class));
    }

    #[Test]
    public function relationship_fields_cannot_be_meta(): void
    {
        MetaFields::for(Post::class, [Select::make('tags')->relationship('tags', 'name')]);

        $this->expectException(MetaException::class);

        MetaFields::fields(Post::class);
    }
}
