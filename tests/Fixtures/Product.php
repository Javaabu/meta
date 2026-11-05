<?php

namespace Javaabu\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Javaabu\Meta\Concerns\HasMeta;

class Product extends Model
{
    use HasMeta;

    protected $guarded = [];

    protected string $meta_table = 'product_meta';
}
