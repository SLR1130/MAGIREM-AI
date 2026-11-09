<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityReportAudit extends Model
{
    protected $fillable = ['report_id', 'moderator_id', 'from_status', 'to_status', 'notes'];

    public function report()
    {
        return $this->belongsTo(CommunityReport::class, 'report_id');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
