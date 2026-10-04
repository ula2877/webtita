<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'period_id',
        'uploaded_by',
        'file_name',
        'sheet_name',
        'total_rows',
        'success_rows',
        'failed_rows',
        'created_users',
        'existing_users',
        'invalid_rows',
        'status',
        'error_details',
    ];

    protected $casts = [
        'error_details' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
