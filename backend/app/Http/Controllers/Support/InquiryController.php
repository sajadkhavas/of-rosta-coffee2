<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\CreateInquiryRequest;
use App\Models\User;
use App\Services\Support\InquiryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class InquiryController extends Controller
{
    public function store(
        CreateInquiryRequest $request,
        InquiryService $inquiries,
    ): JsonResponse {
        $authenticated = $request->user();
        $user = $authenticated instanceof User ? $authenticated : null;
        $result = $inquiries->create(
            $user,
            $request->validated(),
            $request,
        );

        return ApiResponse::success([
            'reference_id' => $result['inquiry']->id,
            'status' => 'received',
            'replayed' => $result['replayed'],
        ], $result['replayed'] ? 200 : 201);
    }
}
