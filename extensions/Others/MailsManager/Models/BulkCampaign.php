<?php

namespace Paymenter\Extensions\Others\MailsManager\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BulkCampaign extends Model
{
    use HasFactory;

    protected $table = 'mailsmanager_bulk_campaigns';

    protected $fillable = [
        'name',
        'subject',
        'body',
        'recipient_type',
        'status',
        'sent_count',
        'total_count',
        'started_at',
        'finished_at',
        'error_message',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'sent_count'  => 'integer',
        'total_count' => 'integer',
    ];

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'done'    => 'success',
            'sending' => 'warning',
            'failed'  => 'danger',
            default   => 'gray',
        };
    }
}
