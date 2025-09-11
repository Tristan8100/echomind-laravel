<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $table = 'evaluations';

    protected $fillable = ['classroom_student_id', 'rating', 'comment', 'sentiment', 'sentiment_score'];

    public function classroomStudent()
    {
        return $this->belongsTo(ClassroomStudent::class);
    }
}
