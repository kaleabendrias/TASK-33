<?php

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'slug',
        'description',
        'group',
    ];
}
