<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function log(string $action, ?array $reference = null, ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'reference' => $reference,
        ]);
    }
}
