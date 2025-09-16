<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $table = 'classrooms';

    protected $fillable = [
        'name',
        'prof_id',
        'subject',
        'description',
        'image',
        'code',
        'sentiment_analysis',
        'ai_analysis',
        'ai_recommendation',
        'status',
    ];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'prof_id');
    }

    public function students()
    {
        return $this->hasMany(ClassroomStudent::class);
    }
}

