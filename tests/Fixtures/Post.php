<?php

namespace Javaabu\Meta\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Javaabu\Meta\Concerns\HasMeta;

class Post extends Model
{
    use HasMeta;
    use SoftDeletes;

    protected $fillable = ['title', 'type'];

    public function metaGroup(): ?string
    {
        return $this->type;
    }
}
