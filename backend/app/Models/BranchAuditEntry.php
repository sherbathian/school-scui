<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BranchAuditEntry extends Model
{
    public $timestamps = false;

    protected $fillable = ['actor_id', 'school_id', 'action', 'target_id', 'before', 'after', 'recorded_at'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'recorded_at' => 'datetime'];
    }
}
