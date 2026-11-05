---
name: javaabu-meta
description: Meta fields for Eloquent models with javaabu/meta. Use when adding custom or meta fields to a model with the HasMeta trait, defining them with MetaFields::for() and javaabu/stencil fields (per model or per group such as a post type, with metaGroup()), reading and writing values (getMeta, setMeta, removeMeta, syncMeta, fillMeta, 'meta' => [...] in create/update), querying or sorting by meta (whereMeta, orWhereMeta, whereMetaIn, whereMetaNotIn, whereHasMeta, whereDoesntHaveMeta, orderByMeta, withMeta, with('meta')), building a meta form with MetaForm in Blade or Inertia React, writing a custom MetaType, or translating meta values with javaabu/translatable.
license: MIT
metadata:
  author: Javaabu
---

# Meta fields with javaabu/meta

The docs ship with the package: `vendor/javaabu/meta/llms.txt` lists every page in `vendor/javaabu/meta/docs/`.

| Task | Read |
| --- | --- |
| Add meta to a model, define, read, write, query, forms | This file |
| Every class, method and scope | [references/api.md](references/api.md) |
| Custom types, translatable meta, storage and performance | [references/extending.md](references/extending.md) |

## The model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;

class Post extends Model
{
    use HasMeta;

    protected $fillable = ['title', 'type'];

    /** Only when records of the model have different fields */
    public function metaGroup(): ?string
    {
        return $this->type;
    }
}
```

## Definitions

In a service provider's `boot()`:

```php
<?php

namespace App\Providers;

use App\Models\Post;
use Illuminate\Support\ServiceProvider;
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Stencil\Fields\Checkbox;
use Javaabu\Stencil\Fields\DatePicker;
use Javaabu\Stencil\Fields\Select;
use Javaabu\Stencil\Fields\Textarea;
use Javaabu\Stencil\Fields\TextInput;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        MetaFields::for(Post::class, [
            TextInput::make('subtitle')->label(__('Subtitle'))->maxLength(255)->thaana(),
            Textarea::make('summary')->label(__('Summary'))->thaana(),
            Checkbox::make('featured')->label(__('Featured')),
            TextInput::make('views')->label(__('Views'))->integer(),
            Select::make('tags')->label(__('Tags'))->multiple()->options([
                'news' => __('News'),
                'sports' => __('Sports'),
            ]),
        ]);

        MetaFields::for(Post::class, [
            DatePicker::make('starts_on')->label(__('Starts on'))->required(),
        ], group: 'event');
    }
}
```

| Stencil field | Type | Read back as |
| --- | --- | --- |
| `TextInput`, single `Select`, `Radio`, `Hidden` | `string` (max 255) | `string` |
| `Textarea`, `Wysiwyg` | `text` | `string` |
| `TextInput::numeric()` | `float` | `float` |
| `TextInput::integer()` | `integer` | `int` |
| `Checkbox` | `boolean` | `bool` |
| `DatePicker` / `DateTimePicker` | `date` / `datetime` | `Carbon` |
| `TimePicker` | `time` | `'H:i:s'` |
| multiple `Select`, `CheckboxList` | `json` | `array` |

`->metaType('float')` overrides the inferred type. `'views' => 'integer'` defines a key by type only. Relationship and media fields can't be meta.

## Reading and writing

```php
$post->getMeta('subtitle');                    // null when missing
$post->getMeta('views', 0);                    // with a default
$post->hasMeta('subtitle');
$post->getAllMeta();

$post->setMeta('subtitle', 'ރާއްޖޭގެ ޚަބަރު');     // written now; no save() needed
$post->setMeta(['featured' => true, 'views' => 10]);
$post->setMeta('subtitle', null);              // removes
$post->removeMeta(['subtitle', 'featured']);
$post->syncMeta(['featured' => true]);         // and removes every other key

Post::create(['title' => 'ސުރުހީ', 'meta' => ['subtitle' => 'ސަބްޓައިޓަލް']]);   // defined keys only, on save
$post->update(['meta' => $request->validated('meta')]);
```

## Querying

```php
$posts = Post::query()
    ->whereMeta('featured', true)
    ->whereMeta('views', '>', 100)
    ->whereMetaIn('tags', ['news'])          // json: contains any
    ->whereHasMeta('subtitle')
    ->orderByMeta('starts_on', 'desc')
    ->with('meta')                            // no N+1 when reading getMeta() in the view
    ->paginate()
    ->withQueryString();
```

## Meta fields in the record's own form

Prefer this when the record has its own stencil form:

```php
use Javaabu\Meta\Facades\MetaFields;

Form::make()->model($post)->schema([
    TextInput::make('title')->label(__('Title'))->required(),
    Section::make(__('Details'), [MetaFields::nested($post)]),   // Group with statePath('meta')
]);
```

Inputs are `meta[subtitle]`, values come from `$post->meta`, rules are keyed `meta.subtitle`, and `validate()` returns `['meta' => [...]]`, which the form's `save()` (or `create()`/`update()`) writes when the post is saved. Conditions inside take full paths: `visibleWhen('meta.featured', true)`. For translatable meta fields, the model implements `Javaabu\Stencil\Contracts\ResolvesTranslatableFields` (`HasMeta` has the method), giving `meta[subtitle_en]`, `meta[subtitle_dv]`.

## A meta form on its own page

```php
<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Javaabu\Meta\Forms\MetaForm;

class PostMetaController extends Controller
{
    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return view('posts.meta', [
            'form' => MetaForm::for($post)->action(route('posts.meta.update', $post))->method('PATCH'),
        ]);
    }

    public function update(Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        MetaForm::for($post)->save();   // validates the request, saves the meta

        return to_route('posts.show', $post);
    }
}
```

Blade: `{{ $form }}`. Inertia: pass `$form` as a prop and render `<StencilForm schema={form} />` from `@javaabu/stencil`. Inside a form you write yourself, pass `MetaForm::for($post)->embedded()` and render it there (`{{ $metaForm }}` / `<StencilFields schema={metaForm} />`). Validate it with `->validate($request)` and save it with `->save($data)` after the record.

## Common mistakes

| Don't | Do |
| --- | --- |
| Add a column or a JSON attribute for each custom field | Define a meta field |
| `$post->setMeta($request->all())` | `$post->fillMeta($validated)` or `MetaForm::for($post)->save()`: defined keys only |
| `$post->setMeta(...); $post->save();` to persist | `setMeta()` already wrote it (save only matters for a new record) |
| `foreach (Post::all() as $post) $post->getMeta(...)` | `Post::with('meta')->get()` or `withMeta([...])` first |
| `->join('meta', …)->where('meta.key', …)` | `whereMeta()`, `orderByMeta()` |
| `(string) $form` | `$form->toHtml()` or `{{ $form }}`: stencil forms aren't `Stringable` |
| `orderByMeta('position')` for an undefined key | Define it, or `orderByMeta('position', 'asc', 'integer')` |
| `whereMeta('color', '!=', 'red')` to include records without a color | Add `->orWhereDoesntHaveMeta('color')` |
| A meta key with the name of one of the record's inputs, in an embedded `MetaForm` | Use `MetaFields::nested()`, or distinct keys |
| Sort a large table by meta on every page | Make that key a real column; keep meta for the long tail |
| Hard-coded labels | `__('Subtitle')`, and `->thaana()` for Dhivehi text |
