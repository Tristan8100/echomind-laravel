<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institute extends Model
{

    protected $table = 'institutes';

    protected $fillable = ['name', 'full_name', 'description'];

    public function professors()
    {
        return $this->hasMany(Professor::class, 'institute_id');
    }
}
