<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Certificate extends Model
{
    use HasFactory;

    protected $fillable = ['worker_id', 'course_id', 'certificate_path', 'issued_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
