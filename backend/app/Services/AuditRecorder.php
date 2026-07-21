<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\RequestFingerprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();
        $requestId = $request->attributes->get('request_id');

        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'request_id' => is_string($requestId) ? $requestId : null,
            'ip_hash' => RequestFingerprint::ip($request),
            'metadata' => $metadata,
        ]);
    }
}
