<?php

namespace Javaabu\Meta\Tests\Fixtures;

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
