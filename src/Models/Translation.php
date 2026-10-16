<?php

namespace Plugins\Glitter\Translate\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $table = 'glitter_translate_translations';

    protected $fillable = [
        'resource_type',
        'resource_id',
        'locale',
        'field',
        'value',
        'content_mode',
        'source_hash',
    ];

    protected $casts = [
        'resource_id' => 'integer',
        'content_mode' => 'string',
    ];
}
