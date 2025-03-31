<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditReportItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function report()
    {
        return $this->belongsTo(AuditReport::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class);
    }
}
