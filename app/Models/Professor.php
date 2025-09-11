<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Professor extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $guard = 'professors';

    protected $table = 'professors';

    protected $fillable = ['name', 'email', 'password', 'image'];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function classrooms()
    {
        return $this->hasMany(Classroom::class, 'prof_id');
    }
}
