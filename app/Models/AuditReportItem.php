<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditReportItem extends Model
{
    protected $fillable = ['file_id', 'key', 'title', 'count', 'items'];

    protected $casts = [
        'items' => 'array',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
