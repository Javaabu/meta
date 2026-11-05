<?php

namespace Javaabu\Meta\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Javaabu\Meta\Exceptions\MetaException;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Meta\Tests\Fixtures\Page;
use Javaabu\Meta\Tests\Fixtures\Post;
use Javaabu\Meta\Tests\Fixtures\Product;
use Javaabu\Meta\Tests\TestCase;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DateTimePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\TextInput;
use PHPUnit\Framework\Attributes\Test;

class QueryScopesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        MetaFields::for(Post::class, [
            TextInput::make('subtitle'),
            Select::make('color')->options(['red' => 'Red', 'blue' => 'Blue', 'green' => 'Green']),
            Checkbox::make('featured'),
            TextInput::make('views')->integer()->metaType('integer'),
            TextInput::make('rating')->numeric(),
            DateTimePicker::make('published_on'),
            Select::make('tags')->multiple()->options(['news' => 'News', 'sports' => 'Sports']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function createPost(string $title, array $meta): Post
    {
        return Post::create(['title' => $title, 'meta' => $meta]);
    }

    protected function seedPosts(): void
    {
        $this->createPost('ފުރަތަމަ', ['subtitle' => 'މާލެ', 'color' => 'red', 'featured' => true, 'views' => 10, 'rating' => 4.5, 'published_on' => '2026-01-10 09:00', 'tags' => ['news']]);
        $this->createPost('ދެވަނަ', ['subtitle' => 'އައްޑު', 'color' => 'blue', 'featured' => false, 'views' => 300, 'rating' => 2.0, 'published_on' => '2026-03-01 09:00', 'tags' => ['sports', 'news']]);
        $this->createPost('ތިންވަނަ', ['color' => 'green', 'views' => 50, 'tags' => ['sports']]);
        $this->createPost('ހަތަރުވަނަ', []);
    }

    /**
     * @return array<int, string>
     */
    protected function titles(Builder $query): array
    {
        return $query->pluck('title')->all();
    }

    #[Test]
    public function where_meta_compares_with_equals_by_default(): void
    {
        $this->seedPosts();

        $this->assertSame(['ފުރަތަމަ'], $this->titles(Post::whereMeta('subtitle', 'މާލެ')));
        $this->assertSame(['ފުރަތަމަ'], $this->titles(Post::whereMeta('featured', true)));
        $this->assertSame(['ދެވަނަ'], $this->titles(Post::whereMeta('featured', false)));
        $this->assertSame(['ދެވަނަ'], $this->titles(Post::whereMeta('color', '=', 'blue')));
    }

    #[Test]
    public function where_meta_takes_operators(): void
    {
        $this->seedPosts();

        $this->assertSame(['ދެވަނަ', 'ތިންވަނަ'], $this->titles(Post::whereMeta('views', '>', 20)->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ', 'ތިންވަނަ'], $this->titles(Post::whereMeta('views', '<=', '50')->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ'], $this->titles(Post::whereMeta('rating', '>=', 3)));
        $this->assertSame(['ދެވަނަ'], $this->titles(Post::whereMeta('published_on', '>', Carbon::parse('2026-02-01'))));
        $this->assertSame(['ދެވަނަ', 'ތިންވަނަ'], $this->titles(Post::whereMeta('color', '!=', 'red')->orderBy('id')));
        $this->assertSame(['ދެވަނަ'], $this->titles(Post::whereMeta('subtitle', 'like', 'އަ%')));
    }

    #[Test]
    public function where_meta_with_null_checks_for_the_key(): void
    {
        $this->seedPosts();

        $this->assertSame(['ތިންވަނަ', 'ހަތަރުވަނަ'], $this->titles(Post::whereMeta('subtitle', null)->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereMeta('subtitle', '!=', null)->orderBy('id')));
    }

    #[Test]
    public function or_where_meta_adds_an_or_condition(): void
    {
        $this->seedPosts();

        $titles = $this->titles(Post::whereMeta('color', 'red')->orWhereMeta('views', '>', 100)->orderBy('id'));

        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $titles);
    }

    #[Test]
    public function where_meta_conditions_combine_with_other_conditions(): void
    {
        $this->seedPosts();

        $titles = $this->titles(Post::where('title', 'ދެވަނަ')->where(fn ($query) => $query->whereMeta('color', 'red')->orWhereMeta('color', 'blue')));

        $this->assertSame(['ދެވަނަ'], $titles);
    }

    #[Test]
    public function where_meta_in_matches_any_value(): void
    {
        $this->seedPosts();

        $this->assertSame(['ފުރަތަމަ', 'ތިންވަނަ'], $this->titles(Post::whereMetaIn('color', ['red', 'green'])->orderBy('id')));
        $this->assertSame(['ދެވަނަ'], $this->titles(Post::whereMetaNotIn('color', ['red', 'green'])));
        $this->assertSame([], $this->titles(Post::whereMetaIn('color', [])));
        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereMetaIn('color', ['red'])->orWhereMetaIn('views', collect([300]))->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereMetaNotIn('color', ['red', 'blue', 'green'])->orWhereMetaNotIn('views', [50])->orderBy('id')));
    }

    #[Test]
    public function json_values_match_lists_that_contain_the_value(): void
    {
        $this->seedPosts();

        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereMeta('tags', 'news')->orderBy('id')));
        $this->assertSame(['ތިންވަނަ'], $this->titles(Post::whereMeta('tags', '!=', 'news')));
        $this->assertSame(['ދެވަނަ', 'ތިންވަނަ'], $this->titles(Post::whereMetaIn('tags', ['sports'])->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ'], $this->titles(Post::whereMetaNotIn('tags', ['sports'])));

        $this->expectException(MetaException::class);

        Post::whereMeta('tags', '>', 'news')->get();
    }

    #[Test]
    public function where_has_meta_checks_for_keys(): void
    {
        $this->seedPosts();

        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereHasMeta(['subtitle', 'rating'])->orderBy('id')));
        $this->assertSame(['ހަތަރުވަނަ'], $this->titles(Post::whereDoesntHaveMeta(['color', 'subtitle'])));
        $this->assertSame(['ތިންވަނަ', 'ހަތަރުވަނަ'], $this->titles(Post::whereHasMeta('nothing')->orWhereDoesntHaveMeta('subtitle')->orderBy('id')));
        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ', 'ތިންވަނަ'], $this->titles(Post::whereHasMeta('nothing')->orWhereHasMeta('views')->orderBy('id')));
    }

    #[Test]
    public function order_by_meta_sorts_by_the_value(): void
    {
        $this->seedPosts();

        $this->assertSame(['ހަތަރުވަނަ', 'ފުރަތަމަ', 'ތިންވަނަ', 'ދެވަނަ'], $this->titles(Post::orderByMeta('views')));
        $this->assertSame(['ދެވަނަ', 'ތިންވަނަ', 'ފުރަތަމަ', 'ހަތަރުވަނަ'], $this->titles(Post::orderByMetaDesc('views')));
        $this->assertSame(['ދެވަނަ', 'ފުރަތަމަ'], $this->titles(Post::whereHasMeta('published_on')->orderByMeta('published_on', 'desc')));
        $this->assertSame(['ފުރަތަމަ', 'ދެވަނަ'], $this->titles(Post::whereMeta('views', '<', 1000)->orderByMeta('rating', 'desc')->limit(2)));
    }

    #[Test]
    public function order_by_meta_keeps_the_models_columns_and_paginates(): void
    {
        $this->seedPosts();

        $posts = Post::orderByMeta('views', 'desc')->orderByMeta('rating')->paginate(2);

        $this->assertSame(4, $posts->total());
        $this->assertSame(['ދެވަނަ', 'ތިންވަނަ'], $posts->pluck('title')->all());
        $this->assertNotNull($posts->first()->id);
        $this->assertSame(['id', 'title', 'type', 'deleted_at', 'created_at', 'updated_at'], array_keys($posts->first()->getAttributes()));
    }

    #[Test]
    public function order_by_meta_needs_a_type_for_undefined_keys(): void
    {
        Page::create(['title' => 'B'])->setMeta('position', 2);
        Page::create(['title' => 'A'])->setMeta('position', 1);

        $this->assertSame(['A', 'B'], Page::orderByMeta('position', 'asc', 'integer')->pluck('title')->all());

        $this->expectException(MetaException::class);

        Page::orderByMeta('position')->get();
    }

    #[Test]
    public function order_by_meta_refuses_bad_directions_and_operators(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Post::orderByMeta('views', 'sideways')->get();
    }

    #[Test]
    public function where_meta_refuses_unknown_operators(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Post::whereMeta('views', 'between', 1)->get();
    }

    #[Test]
    public function undefined_keys_are_queried_in_the_column_of_the_value(): void
    {
        Page::create(['title' => 'A'])->setMeta(['position' => 1, 'label' => 'ހ']);
        Page::create(['title' => 'B'])->setMeta(['position' => 5, 'label' => 'ށ']);

        $this->assertSame(['B'], Page::whereMeta('position', '>', 2)->pluck('title')->all());
        $this->assertSame(['A'], Page::whereMeta('label', 'ހ')->pluck('title')->all());
    }

    #[Test]
    public function queries_only_see_meta_of_their_own_model(): void
    {
        $post = $this->createPost('Post', ['views' => 10]);
        Page::forceCreate(['id' => $post->id, 'title' => 'Page'])->setMeta('views', 99);

        $this->assertSame(['Post'], Post::whereMeta('views', '<', 100)->pluck('title')->all());
        $this->assertSame([], Post::whereMeta('views', 99)->pluck('title')->all());
    }

    #[Test]
    public function group_fields_can_be_queried_from_the_model(): void
    {
        MetaFields::for(Post::class, [TextInput::make('seats')->integer()->metaType('integer')], group: 'event');

        Post::create(['title' => 'Event', 'type' => 'event', 'meta' => ['seats' => 30]]);

        $this->assertSame(['Event'], Post::whereMeta('seats', '>', 20)->orderByMeta('seats')->pluck('title')->all());
    }

    #[Test]
    public function keys_with_different_types_in_different_groups_cannot_be_queried(): void
    {
        MetaFields::for(Post::class, [TextInput::make('code')], group: 'a');
        MetaFields::for(Post::class, [TextInput::make('code')->metaType('integer')], group: 'b');

        $this->expectException(MetaException::class);

        Post::whereMeta('code', 1)->get();
    }

    #[Test]
    public function a_model_with_its_own_meta_table_is_queried_there(): void
    {
        Product::create(['name' => 'Tuna'])->setMeta('weight', 1.5);
        Product::create(['name' => 'Skipjack'])->setMeta('weight', 3.0);

        $this->assertSame(['Skipjack'], Product::whereMeta('weight', '>', 2.0)->pluck('name')->all());
        $this->assertSame(['Skipjack', 'Tuna'], Product::orderByMeta('weight', 'desc', 'float')->pluck('name')->all());
        $this->assertStringContainsString('product_meta', Product::whereMeta('weight', 1.0)->toSql());
    }

    #[Test]
    public function where_meta_is_an_indexed_subquery_on_the_meta_table(): void
    {
        $sql = Post::whereMeta('views', '>', 10)->toRawSql();

        $this->assertMatchesRegularExpression('/"posts"\."id" in \(select "meta"\."metable_id" from "meta" where "meta"\."metable_type" = \'.+Post\' and "meta"\."key" = \'views\' and "value_int" > 10\)/', str_replace('`', '"', $sql));
    }

    /*
    |--------------------------------------------------------------------------
    | Index use
    |--------------------------------------------------------------------------
    */

    protected function seedForPlans(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        $colors = ['red', 'blue', 'green', 'black', 'white'];
        $posts = [];
        $meta = [];

        foreach (range(1, 400) as $id) {
            $posts[] = ['id' => $id, 'title' => "Post {$id}", 'created_at' => $now, 'updated_at' => $now];

            foreach (['color' => ['string', 'value_string', $colors[$id % 5]], 'views' => ['integer', 'value_int', $id * 7 % 1000], 'rating' => ['float', 'value_float', $id % 50 / 10], 'published_on' => ['datetime', 'value_datetime', Carbon::parse('2026-01-01')->addHours($id)->format('Y-m-d H:i:s')]] as $key => [$type, $column, $value]) {
                $meta[] = ['metable_type' => (new Post)->getMorphClass(), 'metable_id' => $id, 'key' => $key, 'locale' => '', 'type' => $type, 'value_string' => null, 'value_int' => null, 'value_float' => null, 'value_datetime' => null, $column => $value, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('posts')->insert($posts);

        foreach (array_chunk($meta, 200) as $chunk) {
            DB::table('meta')->insert($chunk);
        }

        // ANALYZE TABLE would commit the test's transaction on MySQL.
        if (! $this->usingMysql()) {
            DB::statement('analyze');
        }
    }

    /**
     * The indexes the database plans to use for the query.
     *
     * @return array<int, string>
     */
    protected function indexesUsed(Builder $query): array
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        if ($this->usingMysql()) {
            $plan = DB::select("explain format=traditional {$sql}", $bindings);

            return array_values(array_filter(array_map(fn ($row) => $row->key ?? null, $plan)));
        }

        $plan = DB::select("explain query plan {$sql}", $bindings);
        $indexes = [];

        foreach ($plan as $row) {
            if (preg_match('/USING (?:COVERING )?INDEX (\S+)/', $row->detail, $matches)) {
                $indexes[] = $matches[1];
            }
        }

        return $indexes;
    }

    #[Test]
    public function where_meta_uses_the_index_of_the_value_column(): void
    {
        $this->seedForPlans();

        $this->assertContains('meta_string_index', $this->indexesUsed(Post::whereMeta('color', 'red')));
        $this->assertContains('meta_int_index', $this->indexesUsed(Post::whereMeta('views', '>', 990)));
        $this->assertContains('meta_float_index', $this->indexesUsed(Post::whereMeta('rating', '>=', 4.8)));
        $this->assertContains('meta_datetime_index', $this->indexesUsed(Post::whereMeta('published_on', '<', '2026-01-02')));
        $this->assertContains('meta_int_index', $this->indexesUsed(Post::whereMetaIn('views', [7, 14])));
    }

    #[Test]
    public function order_by_meta_joins_on_the_unique_index(): void
    {
        $this->seedForPlans();

        $this->assertContains('meta_record_unique', $this->indexesUsed(Post::orderByMeta('views', 'desc')->limit(10)));
    }

    #[Test]
    public function eager_loading_uses_the_unique_index(): void
    {
        $this->seedForPlans();

        $query = Post::query()->find(1)->meta()->getQuery();

        $relation = Relation::noConstraints(fn () => (new Post)->meta());
        $relation->addEagerConstraints(Post::query()->whereKey([1, 2, 3])->get()->all());

        $this->assertContains('meta_record_unique', $this->indexesUsed($relation->getQuery()));
        $this->assertContains('meta_record_unique', $this->indexesUsed($query));
    }
}
