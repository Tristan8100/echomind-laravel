<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    use HasFactory;

    protected $table = 'survey_questions';

    protected $fillable = [
        'section_id',
        'question_text',
    ];

    public function section()
    {
        return $this->belongsTo(SurveySection::class);
    }

    public function responses()
    {
        return $this->hasMany(SurveyResponse::class, 'survey_question_id');
    }
}
