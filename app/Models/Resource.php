<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
        'content',
        'url',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}
