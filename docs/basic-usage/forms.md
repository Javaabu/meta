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

To edit a post and its meta on one page, embed the meta form in the post's own `<form>`. `->embedded()` renders the fields without a `<form>` or submit button:

```php
// edit(): pass the embedded meta form with the post
return view('posts.edit', ['post' => $post, 'metaForm' => MetaForm::for($post)->embedded()]);
// or Inertia::render('Posts/Edit', ['post' => $post, 'metaForm' => MetaForm::for($post)->embedded()])
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

The meta inputs are named after their keys (`subtitle`, `tags[]`), so a meta key must not be the name of one of the post's own inputs.

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
