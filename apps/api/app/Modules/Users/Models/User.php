<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'role',
        'grade',
        'department',
        'avatar',
    ];

    protected $casts = [
        'email' => 'string',
    ];
}
