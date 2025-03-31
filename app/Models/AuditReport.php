<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditReport extends Model
{
    protected $guarded = [];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function items()
    {
        return $this->hasMany(AuditReportItem::class);
    }
}
