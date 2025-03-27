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

    public function auditReportItems()
    {
        return $this->hasMany(AuditReportItem::class);
    }

    // SCOPES ================================================================================================

    // API ===================================================================================================
}
