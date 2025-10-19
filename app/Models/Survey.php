<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Survey extends Model
{
    use HasFactory;

    protected $table = 'surveys';

    protected $fillable = [
        'title',
        'description',
        'status',
    ];

    public function sections()
    {
        return $this->hasMany(SurveySection::class);
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class);
    }
}
