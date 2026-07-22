<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Content\ContentLinkReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminContentLinkReportController
{
    public function __invoke(
        Request $request,
        CatalogAccess $access,
        ContentLinkReportService $report,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $access->assertAdministrator($user);

        return response()->json(['data' => $report->build()]);
    }
}
