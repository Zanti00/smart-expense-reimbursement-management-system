<?php

namespace App\Modules\Users\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'auth_id',
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
