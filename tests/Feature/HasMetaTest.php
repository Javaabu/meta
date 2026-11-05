<?php

namespace Javaabu\Meta\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Models\Meta;
use Javaabu\Meta\Models\MetaCollection;
use Javaabu\Meta\Tests\Fixtures\Page;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\Fixtures\Product;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;
use PHPUnit\Framework\Attributes\Test;

class HasMetaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MetaFields::for(Post::class, [
            TextInput::make('subtitle')->maxLength(255),
            Checkbox::make('featured'),
            TextInput::make('views')->numeric()->metaType('integer'),
            DatePicker::make('event_date'),
            Textarea::make('summary'),
            Select::make('tags')->multiple()->options(['news' => 'News', 'sports' => 'Sports']),
        ]);
    }

    #[Test]
    public function it_stores_and_reads_typed_values(): void
    {
        $post = Post::create(['title' => 'ސުރުހީ']);

        $post->setMeta([
            'subtitle' => 'ދިވެހި ސަބްޓައިޓަލް',
            'featured' => true,
            'views' => '42',
            'event_date' => '2026-11-05',
            'summary' => str_repeat('ދިގު ލިޔުމެއް ', 50),
            'tags' => ['news', 'sports'],
        ]);

        $post = Post::find($post->id);

        $this->assertSame('ދިވެހި ސަބްޓައިޓަލް', $post->getMeta('subtitle'));
        $this->assertTrue($post->getMeta('featured'));
        $this->assertSame(42, $post->getMeta('views'));
        $this->assertInstanceOf(Carbon::class, $post->getMeta('event_date'));
        $this->assertSame('2026-11-05 00:00:00', $post->getMeta('event_date')->format('Y-m-d H:i:s'));
        $this->assertStringStartsWith('ދިގު ލިޔުމެއް', $post->getMeta('summary'));
        $this->assertSame(['news', 'sports'], $post->getMeta('tags'));

        $row = Meta::query()->where('key', 'views')->first();
        $this->assertSame('integer', $row->type);
        $this->assertEquals(42, $row->value_int);
        $this->assertNull($row->value_string);
        $this->assertSame('', $row->locale);
    }

    #[Test]
    public function it_infers_the_type_of_undefined_keys_from_the_value(): void
    {
        $page = Page::create(['title' => 'Home']);

        $page->setMeta(['count' => 3, 'ratio' => 1.5, 'enabled' => false, 'name' => 'މާލެ', 'list' => [1, 2], 'at' => Carbon::parse('2026-01-02 03:04:05')]);

        $page = Page::find($page->id);

        $this->assertSame(3, $page->getMeta('count'));
        $this->assertSame(1.5, $page->getMeta('ratio'));
        $this->assertFalse($page->getMeta('enabled'));
        $this->assertSame('މާލެ', $page->getMeta('name'));
        $this->assertSame([1, 2], $page->getMeta('list'));
        $this->assertSame('2026-01-02 03:04:05', $page->getMeta('at')->format('Y-m-d H:i:s'));
        $this->assertSame(
            ['count' => 'integer', 'ratio' => 'float', 'enabled' => 'boolean', 'name' => 'string', 'list' => 'json', 'at' => 'datetime'],
            Meta::query()->orderBy('id')->pluck('type', 'key')->all(),
        );
    }

    #[Test]
    public function it_stores_long_undefined_strings_as_text(): void
    {
        $page = Page::create(['title' => 'Home']);

        $page->setMeta('long', str_repeat('a', 300));

        $this->assertSame('text', Meta::query()->value('type'));
        $this->assertSame(300, mb_strlen(Page::find($page->id)->getMeta('long')));
    }

    #[Test]
    public function it_refuses_values_too_long_for_a_string_field(): void
    {
        $post = Post::create(['title' => 'Title']);

        $this->expectException(MetaException::class);

        $post->setMeta('subtitle', str_repeat('a', 256));
    }

    #[Test]
    public function it_updates_values_in_place(): void
    {
        $post = Post::create(['title' => 'Title']);

        $post->setMeta('views', 1);
        $post->setMeta('views', 2);

        $this->assertSame(1, Meta::query()->count());
        $this->assertSame(2, Post::find($post->id)->getMeta('views'));
    }

    #[Test]
    public function it_returns_the_default_when_a_key_has_no_value(): void
    {
        $post = Post::create(['title' => 'Title']);

        $this->assertNull($post->getMeta('subtitle'));
        $this->assertSame('ނެތް', $post->getMeta('subtitle', 'ނެތް'));
        $this->assertFalse($post->hasMeta('subtitle'));
    }

    #[Test]
    public function null_removes_a_value(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta(['subtitle' => 'ސަބް', 'views' => 3]);

        $post->setMeta('subtitle', null);

        $this->assertFalse($post->hasMeta('subtitle'));
        $this->assertTrue($post->hasMeta('views'));
        $this->assertSame(['views'], Meta::query()->pluck('key')->all());
    }

    #[Test]
    public function it_removes_and_syncs_keys(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta(['subtitle' => 'ސަބް', 'views' => 3, 'featured' => true]);

        $post->removeMeta(['subtitle', 'featured']);
        $this->assertSame(['views' => 3], Post::find($post->id)->getAllMeta());

        $post->syncMeta(['featured' => false, 'summary' => 'ލިޔުން']);
        $this->assertEqualsCanonicalizing(['featured' => false, 'summary' => 'ލިޔުން'], Post::find($post->id)->getAllMeta());
    }

    #[Test]
    public function reads_see_writes_on_loaded_meta(): void
    {
        $post = Post::create(['title' => 'Title']);

        $this->assertNull($post->getMeta('subtitle'));

        $post->setMeta('subtitle', 'ސަބް');

        $this->assertSame('ސަބް', $post->getMeta('subtitle'));
    }

    #[Test]
    public function meta_set_before_the_record_is_saved_is_written_when_it_is(): void
    {
        $post = new Post(['title' => 'Title']);
        $post->setMeta('subtitle', 'ސަބް');

        $this->assertTrue($post->hasPendingMeta());
        $this->assertSame(0, Meta::query()->count());

        $post->save();

        $this->assertFalse($post->hasPendingMeta());
        $this->assertSame('ސަބް', Post::find($post->id)->getMeta('subtitle'));
    }

    #[Test]
    public function meta_can_be_mass_assigned_with_the_record(): void
    {
        $post = Post::create(['title' => 'Title', 'meta' => ['subtitle' => 'ސަބް', 'featured' => '1', 'unknown' => 'x']]);

        $this->assertEquals(['subtitle' => 'ސަބް', 'featured' => true], Post::find($post->id)->getAllMeta());

        $post->update(['meta' => ['views' => 5]]);
        $this->assertSame(5, Post::find($post->id)->getMeta('views'));

        $post->fill(['meta' => ['subtitle' => null]])->save();
        $this->assertFalse(Post::find($post->id)->hasMeta('subtitle'));
    }

    #[Test]
    public function mass_assignment_works_with_guarded_models(): void
    {
        MetaFields::for(Page::class, [TextInput::make('heading')]);

        $page = Page::create(['title' => 'Home', 'meta' => ['heading' => 'މަރުހަބާ']]);

        $this->assertSame('މަރުހަބާ', Page::find($page->id)->getMeta('heading'));
    }

    #[Test]
    public function mass_assigning_undefined_meta_throws_when_discarding_attributes_is_prevented(): void
    {
        Model::preventSilentlyDiscardingAttributes();

        try {
            $this->expectException(MetaException::class);

            Post::create(['title' => 'Title', 'meta' => ['unknown' => 'x']]);
        } finally {
            Model::preventSilentlyDiscardingAttributes(false);
        }
    }

    #[Test]
    public function group_fields_apply_to_records_of_the_group(): void
    {
        MetaFields::for(Post::class, [DatePicker::make('starts_on')], group: 'event');

        $event = Post::create(['title' => 'Event', 'type' => 'event', 'meta' => ['starts_on' => '2026-12-01', 'subtitle' => 'ސަބް']]);
        $news = Post::create(['title' => 'News', 'type' => 'news', 'meta' => ['starts_on' => '2026-12-01']]);

        $this->assertSame('2026-12-01', Post::find($event->id)->getMeta('starts_on')->format('Y-m-d'));
        $this->assertSame('ސަބް', Post::find($event->id)->getMeta('subtitle'));
        $this->assertFalse(Post::find($news->id)->hasMeta('starts_on'));
    }

    #[Test]
    public function the_meta_collection_reads_values_by_key(): void
    {
        $post = Post::create(['title' => 'Title']);
        $post->setMeta(['subtitle' => 'ސަބް', 'views' => 3]);

        $post = Post::find($post->id);

        $this->assertInstanceOf(MetaCollection::class, $post->meta);
        $this->assertSame('ސަބް', $post->meta['subtitle']);
        $this->assertTrue(isset($post->meta['views']));
        $this->assertFalse(isset($post->meta['missing']));
        $this->assertSame('ސަބް', data_get($post, 'meta.subtitle'));
        $this->assertSame(3, data_get($post, 'meta.views'));
        $this->assertInstanceOf(Meta::class, $post->meta[0]);
    }

    #[Test]
    public function eager_loading_reads_meta_without_a_query_per_record(): void
    {
        foreach (range(1, 5) as $i) {
            Post::create(['title' => "Post {$i}", 'meta' => ['subtitle' => "ސަބް {$i}", 'views' => $i]]);
        }

        DB::enableQueryLog();

        $posts = Post::with('meta')->get();
        $subtitles = $posts->map->getMeta('subtitle')->all();

        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame(['ސަބް 1', 'ސަބް 2', 'ސަބް 3', 'ސަބް 4', 'ސަބް 5'], $subtitles);

        DB::flushQueryLog();

        $posts = Post::withMeta(['views'])->get();

        $this->assertSame([1, 2, 3, 4, 5], $posts->map->getMeta('views')->all());
        $this->assertNull($posts->first()->getMeta('subtitle'));
        $this->assertCount(2, DB::getQueryLog());
    }

    #[Test]
    public function deleting_a_record_deletes_its_meta(): void
    {
        $page = Page::create(['title' => 'Home']);
        $page->setMeta('a', 1);

        $page->delete();

        $this->assertSame(0, Meta::query()->count());
    }

    #[Test]
    public function soft_deleting_keeps_meta_until_force_deleted(): void
    {
        $post = Post::create(['title' => 'Title', 'meta' => ['views' => 1]]);

        $post->delete();
        $this->assertSame(1, Meta::query()->count());

        $post->forceDelete();
        $this->assertSame(0, Meta::query()->count());
    }

    #[Test]
    public function records_of_different_models_keep_separate_meta(): void
    {
        $post = Post::create(['title' => 'Title']);
        $page = Page::forceCreate(['id' => $post->id, 'title' => 'Home']);

        $post->setMeta('views', 1);
        $page->setMeta('views', 2);

        $this->assertSame(1, Post::find($post->id)->getMeta('views'));
        $this->assertSame(2, Page::find($page->id)->getMeta('views'));
    }

    #[Test]
    public function a_model_can_keep_its_meta_in_its_own_table(): void
    {
        $product = Product::create(['name' => 'Tuna']);
        $product->setMeta('weight', 1.25);

        $this->assertSame(0, Meta::query()->count());
        $this->assertSame(1, DB::table('product_meta')->count());
        $this->assertSame(1.25, Product::with('meta')->find($product->id)->getMeta('weight'));
        $this->assertSame('product_meta', $product->meta()->getRelated()->getTable());
    }
}
