<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['quiz_id', 'question', 'options', 'correct_answer', 'points', 'order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'integer',
            'points' => 'integer',
            'order' => 'integer',
        ];
    }

    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}
