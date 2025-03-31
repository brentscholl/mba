<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'DOS' => 'date',
        'PaymentDate' => 'date',
        'ProviderAmountEach' => 'decimal:2',
        'ProviderAmountTotal' => 'decimal:2',
        'LineItemAPBalance' => 'decimal:2',
        'AppliedAPAmount' => 'decimal:2',
    ];

    // RELATIONSHIPS =========================================================================================

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function auditReportItems()
    {
        return $this->belongsToMany(AuditReportItem::class);
    }

    // SCOPES ================================================================================================

    // API ===================================================================================================
}
