<?php

namespace Javaabu\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;

class Page extends Model
{
    use HasMeta;

    protected $guarded = ['id'];
}
