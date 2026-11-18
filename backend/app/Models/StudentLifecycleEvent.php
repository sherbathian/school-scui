<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLifecycleEvent extends Model
{
    protected $fillable = ['school_id', 'student_id', 'event_type', 'event_date', 'reason'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
