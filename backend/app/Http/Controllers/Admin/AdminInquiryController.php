<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\UpdateInquiryStatusRequest;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Support\InquiryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminInquiryController extends Controller
{
    public function index(
        Request $request,
        CatalogAccess $access,
        InquiryService $inquiries,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $status = trim((string) $request->query('status', 'new'));
        $query = Inquiry::query()->latest('created_at');
        if (in_array($status, array_column(InquiryStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(
            perPage: max(1, min(100, (int) $request->query('per_page', 50))),
            page: max(1, (int) $request->query('page', 1)),
        );

        return ApiResponse::success([
            'items' => $page->getCollection()
                ->map(fn (Inquiry $inquiry): array => $inquiries->adminPayload($inquiry))
                ->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(
        Request $request,
        string $inquiryId,
        CatalogAccess $access,
        InquiryService $inquiries,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return ApiResponse::success($inquiries->adminPayload(
            Inquiry::query()->findOrFail($inquiryId),
        ));
    }

    public function updateStatus(
        UpdateInquiryStatusRequest $request,
        string $inquiryId,
        CatalogAccess $access,
        InquiryService $inquiries,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);
        $inquiry = Inquiry::query()->findOrFail($inquiryId);
        $updated = $inquiries->updateStatus(
            $user,
            $inquiry,
            InquiryStatus::from((string) $request->validated('status')),
            $request,
        );

        return ApiResponse::success($inquiries->adminPayload($updated));
    }
}
