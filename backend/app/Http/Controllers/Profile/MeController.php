<?php

namespace App\Http\Controllers\Profile;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\AuditRecorder;
use Illuminate\Http\Request;

final class MeController
{
    public function show(Request $request): AuthUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new AuthUserResource($user->load('roleAssignments'));
    }

    public function update(
        UpdateProfileRequest $request,
        AuditRecorder $audit,
    ): AuthUserResource {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $changedFields = [];

        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $validated) && $user->getAttribute($field) !== $validated[$field]) {
                $user->setAttribute($field, $validated[$field]);
                $changedFields[] = $field;
            }
        }

        if ($changedFields !== []) {
            $user->save();
            $audit->record(
                'identity.profile.updated',
                actor: $user,
                auditable: $user,
                metadata: ['fields' => $changedFields],
                request: $request,
            );
        }

        return new AuthUserResource($user->load('roleAssignments'));
    }
}
