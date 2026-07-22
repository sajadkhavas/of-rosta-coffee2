<?php

namespace App\Services\Content;

use App\Exceptions\ApiDomainException;
use App\Models\SeoRedirect;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\SeoPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SeoRedirectService
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor, Request $request): SeoRedirect
    {
        return DB::transaction(function () use ($data, $actor, $request): SeoRedirect {
            $normalized = $this->normalize($data);
            $this->assertNoLoop(
                $normalized['source_path'],
                $normalized['destination_path'],
            );

            $redirect = SeoRedirect::query()->create([
                ...$normalized,
                'created_by' => $actor->id,
                'hits' => 0,
            ]);

            $this->audit->record(
                'seo.redirect.created',
                actor: $actor,
                auditable: $redirect,
                metadata: [
                    'source_path' => $redirect->source_path,
                    'destination_path' => $redirect->destination_path,
                ],
                request: $request,
            );

            return $redirect;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(
        SeoRedirect $redirect,
        array $data,
        User $actor,
        Request $request,
    ): SeoRedirect {
        return DB::transaction(function () use ($redirect, $data, $actor, $request): SeoRedirect {
            $locked = SeoRedirect::query()
                ->lockForUpdate()
                ->findOrFail($redirect->id);
            $normalized = $this->normalize([
                'source_path' => $data['source_path'] ?? $locked->source_path,
                'destination_path' => $data['destination_path'] ?? $locked->destination_path,
                'status_code' => $data['status_code'] ?? $locked->status_code,
                'is_active' => $data['is_active'] ?? $locked->is_active,
            ]);
            $this->assertNoLoop(
                $normalized['source_path'],
                $normalized['destination_path'],
                $locked->id,
            );

            $locked->fill($normalized)->save();
            $this->audit->record(
                'seo.redirect.updated',
                actor: $actor,
                auditable: $locked,
                metadata: ['fields' => array_keys($data)],
                request: $request,
            );

            return $locked->refresh();
        });
    }

    public function resolve(string $path): ?SeoRedirect
    {
        $normalized = SeoPath::assertPublic($path);
        $redirect = SeoRedirect::query()
            ->where('source_path', $normalized)
            ->where('is_active', true)
            ->first();

        if (! $redirect instanceof SeoRedirect) {
            return null;
        }

        SeoRedirect::query()
            ->whereKey($redirect->id)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_hit_at' => now(),
            ]);

        return $redirect->forceFill([
            'hits' => $redirect->hits + 1,
            'last_hit_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): array
    {
        return [
            'source_path' => SeoPath::assertPublic(
                (string) $data['source_path'],
            ),
            'destination_path' => SeoPath::assertPublic(
                (string) $data['destination_path'],
            ),
            'status_code' => (int) ($data['status_code'] ?? 301),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function assertNoLoop(
        string $source,
        string $destination,
        ?string $ignoreId = null,
    ): void {
        if ($source === $destination) {
            throw new ApiDomainException(
                'seo.redirect_loop',
                'مبدأ و مقصد Redirect یکسان است.',
                422,
            );
        }

        $visited = [$source => true];
        $cursor = $destination;
        for ($depth = 0; $depth < 12; $depth++) {
            if (isset($visited[$cursor])) {
                throw new ApiDomainException(
                    'seo.redirect_loop',
                    'زنجیره Redirect حلقه ایجاد می‌کند.',
                    422,
                );
            }
            $visited[$cursor] = true;

            $query = SeoRedirect::query()
                ->where('source_path', $cursor)
                ->where('is_active', true);
            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }
            $next = $query->first();
            if (! $next instanceof SeoRedirect) {
                return;
            }
            $cursor = $next->destination_path;
        }

        throw new ApiDomainException(
            'seo.redirect_chain_too_long',
            'زنجیره Redirect بیش از حد طولانی است.',
            422,
        );
    }
}
