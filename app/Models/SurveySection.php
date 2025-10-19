<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveySection extends Model
{
    use HasFactory;

    protected $table = 'survey_sections';

    protected $fillable = [
        'survey_id',
        'title',
        'description',
    ];

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    public function questions()
    {
        return $this->hasMany(SurveyQuestion::class, 'section_id');
    }
}
