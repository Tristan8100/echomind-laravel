<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{

    protected $table = 'conversations';

    protected $fillable = ['professor_id', 'title'];

    public function professor()
    {
        return $this->belongsTo(Professor::class, 'professor_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}
