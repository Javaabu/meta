---
title: Meta forms
sidebar_position: 5
---

`MetaForm` is a [stencil](https://github.com/Javaabu/stencil) form of a record's meta fields:

- its fields come from the [definitions](./defining-fields.md) (the model's own and the record's group)
- its values come from the record's meta
- saving it validates the input and saves the meta

It renders with stencil's Blade or React driver, like any other stencil form.

```php
use Javaabu\Meta\Forms\MetaForm;

$form = MetaForm::for($post);                 // or MetaFields::form($post)
$form = MetaForm::for($post, group: 'event'); // another group's fields
```

# Blade

```php
// PostMetaController
public function edit(Post $post)
{
    return view('posts.meta', [
        'post' => $post,
        'form' => MetaForm::for($post)->action(route('posts.meta.update', $post))->method('PATCH'),
    ]);
}

public function update(Post $post)
{
    MetaForm::for($post)->save();   // validates the request, saves the meta

    return to_route('posts.show', $post);
}
```

```blade
{{-- resources/views/posts/meta.blade.php --}}
{{ $form }}
```

The form renders with [javaabu/forms](https://github.com/Javaabu/forms) components and its submit button says "Save" ("ސޭވް ކުރައްވާ" in Dhivehi). Validation errors and old input work as in any stencil form.

# Inertia React

```php
public function edit(Post $post)
{
    return Inertia::render('Posts/Meta', [
        'form' => MetaForm::for($post)->action(route('posts.meta.update', $post))->method('PATCH'),
    ]);
}
```

```tsx
// resources/js/pages/Posts/Meta.tsx
import { StencilForm, type StencilSchema } from "@javaabu/stencil"

export default function Meta({ form }: { form: StencilSchema }) {
  return <StencilForm schema={form} />
}
```

The `update` action is the same as for Blade. See stencil's [React docs](https://github.com/Javaabu/stencil/blob/main/docs/basic-usage/react.md) for setting up the renderer.

# Inside the record's own form

To edit a post and its meta on one page, put the meta fields in the post's own stencil form. `MetaFields::nested($post)` is a stencil `Group` of the record's meta fields (with its group's), nested under `meta` with stencil's `statePath()`:

```php
use Javaabu\Meta\Facades\MetaFields;
use Javaabu\Stencil\Fields\TextInput;
use Javaabu\Stencil\Form;

class PostForm extends Form
{
    protected function definition(): array
    {
        return [
            TextInput::make('title')->required(),
            MetaFields::nested($this->getRecord() ?? new Post),
        ];
    }
}
```

- **Names:** the inputs are named `meta[subtitle]` (state path `meta.subtitle`, id `meta-subtitle`), so they never clash with the post's own inputs.
- **Values:** they're read from `$post->meta`, by key (`data_get($post, 'meta.subtitle')`).
- **Validation:** the rules are keyed `meta.subtitle`, and `validate()` returns `['title' => …, 'meta' => ['subtitle' => …]]`.
- **Saving:** stencil's `save()`, or `Post::create($data)` and `$post->update($data)`, pass `meta` to the model, which writes the defined keys when the post is saved. A new post gets its meta when it's created.
- **Lay it out:** the group is an ordinary component, so put it in a section or a tab: `Section::make(__('Details'), [MetaFields::nested($post)])`. `MetaFields::formFields($post)` gives the fields without the group, for a layout of your own (then nest them with `statePath('meta')` yourself).

```php
public function update(Post $post)
{
    PostForm::make()->model($post)->save();   // validates, saves the post and its meta

    return to_route('posts.show', $post);
}
```

Fields of another group: `MetaFields::nested($post, group: 'event')`. With a model class instead of a record, only the model's own fields are given, unless you pass the group.

:::note
Stencil conditions take full state paths. In a field nested this way, `visibleWhen('meta.featured', true)` watches the `featured` meta field. In a `MetaForm`, the same field is `featured`. Give a definition the path for the form you put it in.
:::

## Translatable fields in the record's form

Stencil gives a translatable meta field one input per language when the form's record says which fields are translatable. Implement stencil's `ResolvesTranslatableFields` on the model. `HasMeta` provides the method:

```php
use Javaabu\Meta\Concerns\HasMeta;
use Javaabu\Stencil\Contracts\ResolvesTranslatableFields;

class Post extends Model implements ResolvesTranslatableFields
{
    use HasMeta;
}
```

`HasMeta::isTranslatableField()` answers for `meta.*` paths from the definitions, and for the model's own fields from javaabu/translatable, so a translatable model's own fields keep working. The inputs are named `meta[subtitle_en]` and `meta[subtitle_dv]`. See [Translatable meta](../advanced-usage/translatable.md#in-the-records-own-form).

## Embedding a meta form

You can also render a `MetaForm` without a `<form>` (`->embedded()`) inside a form you write yourself, such as a plain Blade form. Its inputs aren't nested (`subtitle`, `tags[]`), so a meta key must not be the name of one of the post's own inputs, and you validate and save it separately:

```php
// edit(): pass the embedded meta form with the post
return view('posts.edit', ['post' => $post, 'metaForm' => MetaForm::for($post)->embedded()]);
```

```blade
<x-forms::form :action="route('posts.update', $post)" method="PATCH" :model="$post">
    <x-forms::text name="title" required />

    {{ $metaForm }}

    <x-forms::submit>{{ __('Save') }}</x-forms::submit>
</x-forms::form>
```

```tsx
import { Form, Submit, Text } from "@javaabu/forms"
import { __ } from "@javaabu/i18n"
import { StencilFields, type StencilSchema } from "@javaabu/stencil"

export default function Edit({ post, metaForm }: { post: Post; metaForm: StencilSchema }) {
  return (
    <Form action={`/posts/${post.id}`} method="patch" model={post}>
      <Text name="title" required />
      <StencilFields schema={metaForm} />
      <Submit>{__("Save")}</Submit>
    </Form>
  )
}
```

```php
public function update(PostRequest $request, Post $post)
{
    $meta = MetaForm::for($post)->validate($request);   // validate both before saving either

    $post->update($request->validated());
    MetaForm::for($post)->save($meta);

    return to_route('posts.show', $post);
}
```

A new record works the same way. `MetaForm::for(new Post)` has no values, and the meta is saved when the post is:

```php
$post = new Post($request->validated());
MetaForm::for($post)->save();   // waits for the post
$post->save();                  // writes the meta too
```

# Validating and saving yourself

```php
$form = MetaForm::for($post);

$form->rules();                     // ['subtitle' => ['required', 'string', 'max:100', 'max:255'], …]
$data = $form->validate($request);  // throws a ValidationException
$form->save($data);                 // saves; returns the record
$post->fillMeta($data);             // the same, without the form
```

Rules come from the fields and their types. A string field also gets `max:255`, an integer field `integer`, and a date field `date`. Validation messages use the fields' labels.

An empty optional field removes its meta: saving `views` as `null` deletes the value.

# Live fields

`MetaForm` binds an array of values (or, with translatable fields, a record that's never saved), so stencil's live fields (`->live()`, `afterStateUpdated()`) can't rebuild it. Conditions (`visibleWhen()` and friends) work.
