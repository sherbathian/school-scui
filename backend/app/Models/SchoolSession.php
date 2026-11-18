<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

// school_id is supplied by the authorized school relationship. The controller
// validates is_current and derives current_marker from that same school.
#[Fillable(['title', 'starts_on', 'ends_on', 'is_current', 'current_marker', 'status'])]
class SchoolSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_current' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Make this the school's only current session. */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            // Clear the old one first, so the unique marker is free again.
            static::where('school_id', $this->school_id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'current_marker' => null]);

            $this->forceFill([
                'is_current' => true,
                'current_marker' => $this->school_id,
            ])->save();
        });
    }
}
