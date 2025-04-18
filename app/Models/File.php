<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $guarded = [];

    // RELATIONSHIPS =========================================================================================

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function auditReports()
    {
        return $this->hasMany(\App\Models\AuditReport::class);
    }

    // SCOPES ================================================================================================

    // API ===================================================================================================
}
