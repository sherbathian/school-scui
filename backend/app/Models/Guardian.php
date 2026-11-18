<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guardian extends Model
{
    use SoftDeletes;

    protected $fillable = ['school_id', 'source_parent_id', 'source_data', 'father_name', 'father_phone', 'father_occupation', 'father_cnic', 'father_doc_path', 'mother_name', 'mother_phone', 'mother_occupation', 'mother_cnic', 'mother_doc_path', 'is_guardian_other', 'guardian_name', 'guardian_relation', 'guardian_email', 'guardian_phone', 'guardian_occupation', 'guardian_cnic', 'guardian_address', 'guardian_doc_path'];

    protected function casts(): array
    {
        return ['source_data' => 'array', 'is_guardian_other' => 'boolean'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function familyFeeReceipts(): HasMany
    {
        return $this->hasMany(FamilyFeeReceipt::class);
    }
}
