<?php

namespace Javaabu\Meta\Tests\Unit;

use Illuminate\Support\Carbon;
use Javaabu\Meta\Enums\MetaColumn;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Tests\Fixtures\Color;
use Javaabu\Meta\Tests\Fixtures\ColorPicker;
use Javaabu\Meta\Tests\Fixtures\ColorType;
use Javaabu\Meta\Tests\Fixtures\Page;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Meta\Types\StringType;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\CheckboxList;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\DateTimePicker;
use Javaabu\Stencil\Fields\Hidden;
use Javaabu\Stencil\Fields\Radio;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Fields\TimePicker;
use Javaabu\Stencil\Fields\Wysiwyg;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

enum Status: string
{
    case Draft = 'draft';
}

class TypesTest extends TestCase
{
    #[Test]
    public function it_registers_the_built_in_types(): void
    {
        $this->assertSame(
            ['text', 'string', 'json', 'float', 'integer', 'boolean', 'date', 'datetime', 'time'],
            array_keys(MetaFields::types()),
        );
    }

    /**
     * @return array<string, array{0: callable, 1: string}>
     */
    public static function fields(): array
    {
        return [
            'text input' => [fn () => TextInput::make('a'), 'string'],
            'email input' => [fn () => TextInput::make('a')->email(), 'string'],
            'numeric input' => [fn () => TextInput::make('a')->numeric(), 'float'],
            'textarea' => [fn () => Textarea::make('a'), 'text'],
            'wysiwyg' => [fn () => Wysiwyg::make('a'), 'text'],
            'checkbox' => [fn () => Checkbox::make('a'), 'boolean'],
            'checkbox list' => [fn () => CheckboxList::make('a'), 'json'],
            'select' => [fn () => Select::make('a'), 'string'],
            'multiple select' => [fn () => Select::make('a')->multiple(), 'json'],
            'radio' => [fn () => Radio::make('a'), 'string'],
            'hidden' => [fn () => Hidden::make('a'), 'string'],
            'date' => [fn () => DatePicker::make('a'), 'date'],
            'datetime' => [fn () => DateTimePicker::make('a'), 'datetime'],
            'time' => [fn () => TimePicker::make('a'), 'time'],
        ];
    }

    #[Test]
    #[DataProvider('fields')]
    public function it_infers_the_type_of_a_stencil_field(callable $field, string $type): void
    {
        $this->assertSame($type, MetaFields::fieldType($field())->name());
    }

    #[Test]
    public function meta_type_overrides_the_inferred_type(): void
    {
        $field = TextInput::make('views')->numeric()->metaType('integer');

        $this->assertSame('integer', MetaFields::fieldType($field)->name());
    }

    /**
     * @return array<string, array{0: callable, 1: string}>
     */
    public static function values(): array
    {
        return [
            'int' => [fn () => 1, 'integer'],
            'float' => [fn () => 1.5, 'float'],
            'bool' => [fn () => true, 'boolean'],
            'string' => [fn () => 'ހ', 'string'],
            'long string' => [fn () => str_repeat('ހ', 256), 'text'],
            'array' => [fn () => ['a'], 'json'],
            'collection' => [fn () => collect(['a']), 'json'],
            'date' => [fn () => Carbon::now(), 'datetime'],
            'string enum' => [fn () => Status::Draft, 'string'],
        ];
    }

    #[Test]
    #[DataProvider('values')]
    public function it_infers_the_type_of_a_value(callable $value, string $type): void
    {
        $this->assertSame($type, MetaFields::typeForValue($value())->name());
    }

    #[Test]
    public function types_serialize_and_unserialize_values(): void
    {
        $this->assertSame('draft', MetaFields::getType('string')->serialize(Status::Draft));
        $this->assertSame('1', MetaFields::getType('string')->serialize(true));
        $this->assertSame(12, MetaFields::getType('integer')->serialize('12'));
        $this->assertSame(1, MetaFields::getType('integer')->serialize(true));
        $this->assertSame(1.25, MetaFields::getType('float')->serialize('1.25'));
        $this->assertSame(1, MetaFields::getType('boolean')->serialize('on'));
        $this->assertSame(0, MetaFields::getType('boolean')->serialize('false'));
        $this->assertSame(0, MetaFields::getType('boolean')->serialize(null ?? 0));
        $this->assertSame('2026-11-05 00:00:00', MetaFields::getType('date')->serialize('2026-11-05 15:30'));
        $this->assertSame('2026-11-05 15:30:00', MetaFields::getType('datetime')->serialize('2026-11-05 15:30'));
        $this->assertSame('2026-11-05 15:30:00', MetaFields::getType('datetime')->serialize(Carbon::parse('2026-11-05 15:30')->getTimestamp()));
        $this->assertSame('15:30:00', MetaFields::getType('time')->serialize('15:30'));
        $this->assertSame('09:05:00', MetaFields::getType('time')->serialize(Carbon::parse('2026-01-01 09:05')));
        $this->assertSame('["ހ",1]', MetaFields::getType('json')->serialize(['ހ', 1]));
        $this->assertSame(['a' => 1], MetaFields::getType('json')->unserialize('{"a":1}'));
        $this->assertSame('ލިޔުން', MetaFields::getType('text')->serialize('ލިޔުން'));
        $this->assertSame('15:30:00', MetaFields::getType('time')->unserialize('15:30:00'));
        $this->assertTrue(MetaFields::getType('boolean')->unserialize('1'));
    }

    /**
     * @return array<string, array{0: string, 1: callable}>
     */
    public static function invalidValues(): array
    {
        return [
            'string from object' => ['string', fn () => new stdClass],
            'text from array' => ['text', fn () => ['a']],
            'integer from word' => ['integer', fn () => 'many'],
            'float from word' => ['float', fn () => 'lots'],
            'date from word' => ['date', fn () => 'not a date'],
            'date from array' => ['datetime', fn () => ['a']],
            'time from word' => ['time', fn () => 'teatime'],
            'time from int' => ['time', fn () => 5],
            'json from string' => ['json', fn () => 'a'],
        ];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function types_refuse_values_they_cannot_store(string $type, callable $value): void
    {
        $this->expectException(MetaException::class);

        MetaFields::getType($type)->serialize($value(), 'key');
    }

    #[Test]
    public function types_give_their_column_rules_and_default_field(): void
    {
        $this->assertSame(MetaColumn::Integer, MetaFields::getType('boolean')->column());
        $this->assertSame(MetaColumn::DateTime, MetaFields::getType('date')->column());
        $this->assertSame(MetaColumn::String, MetaFields::getType('time')->column());
        $this->assertSame(['max:255'], MetaFields::getType('string')->rules());
        $this->assertSame(['integer'], MetaFields::getType('integer')->rules());
        $this->assertSame([], MetaFields::getType('text')->rules());
        $this->assertInstanceOf(Checkbox::class, MetaFields::getType('boolean')->field('featured'));
        $this->assertInstanceOf(DatePicker::class, MetaFields::getType('date')->field('on'));
        $this->assertInstanceOf(Select::class, MetaFields::getType('json')->field('tags'));
        $this->assertSame('views', MetaFields::getType('integer')->field('views')->getName());
        $this->assertTrue(MetaColumn::String->isIndexed());
        $this->assertFalse(MetaColumn::Json->isIndexed());
    }

    #[Test]
    public function a_custom_type_stores_reads_and_queries_values(): void
    {
        MetaFields::type(ColorType::class);
        MetaFields::for(Post::class, [ColorPicker::make('accent'), 'background' => 'color']);

        $this->assertSame('color', MetaFields::field(Post::class, 'accent')->type->name());
        $this->assertSame('color', MetaFields::field(Post::class, 'background')->type->name());
        $this->assertSame('#000000', MetaFields::field(Post::class, 'background')->field->getPlaceholder());

        $post = Post::create(['title' => 'ކުލަ', 'meta' => ['accent' => 'ff0000', 'background' => '#00ff00']]);

        $post = Post::find($post->id);

        $this->assertEquals(new Color('#FF0000'), $post->getMeta('accent'));
        $this->assertSame(['ކުލަ'], Post::whereMeta('accent', '#ff0000')->pluck('title')->all());
        $this->assertSame(['ކުލަ'], Post::orderByMeta('background')->pluck('title')->all());

        $page = Page::create(['title' => 'Page']);
        $page->setMeta('tint', new Color('#123456'));
        $this->assertEquals(new Color('#123456'), Page::find($page->id)->getMeta('tint'));
    }

    #[Test]
    public function custom_types_can_be_registered_in_config(): void
    {
        config()->set('meta.types', [ColorType::class]);
        $this->app->forgetInstance(\Javaabu\Meta\MetaFields::class);
        MetaFields::clearResolvedInstances();

        $this->assertTrue(MetaFields::hasType('color'));
    }

    #[Test]
    public function a_type_with_the_name_of_a_built_in_type_replaces_it(): void
    {
        $short = new class extends StringType
        {
            public const int MAX_LENGTH = 10;
        };

        MetaFields::type($short);

        $this->assertSame($short, MetaFields::getType('string'));
        $this->assertSame(['max:10'], MetaFields::getType('string')->rules());
    }

    #[Test]
    public function unknown_and_invalid_types_throw(): void
    {
        $this->expectException(MetaException::class);

        MetaFields::getType('nope');
    }

    #[Test]
    public function registering_a_class_that_is_not_a_type_throws(): void
    {
        $this->expectException(MetaException::class);

        MetaFields::type(stdClass::class);
    }
}
