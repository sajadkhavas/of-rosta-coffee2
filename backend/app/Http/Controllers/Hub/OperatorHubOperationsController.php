<?php

namespace App\Http\Controllers\Hub;

use App\Enums\HubWorkItemAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hub\TransitionHubWorkItemRequest;
use App\Http\Resources\HubWorkItemResource;
use App\Models\HubWorkItem;
use App\Models\User;
use App\Services\Hub\RostaHubOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorHubOperationsController extends Controller
{
    public function index(Request $request, RostaHubOperationsService $operations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $operations->backfillMissing();
        $items = HubWorkItem::query()->where('assigned_operator_id', $user->id)->with(['hub', 'assignedOperator:id,name', 'order:id,order_number', 'subOrder:id,roastery_id,status', 'orderItemService.grindingProfile', 'inboundShipmentLeg', 'outboundShipmentLeg', 'actions.actor:id,name'])->latest()->limit(100)->get();

        return ApiResponse::success(['items' => HubWorkItemResource::collection($items)->resolve($request)]);
    }

    public function transition(TransitionHubWorkItemRequest $request, string $workItemId, RostaHubOperationsService $operations): HubWorkItemResource
    {
        /** @var User $user */ $user = $request->user();

        return new HubWorkItemResource($operations->transition($user, $workItemId, HubWorkItemAction::from((string) $request->validated('action')), (string) $request->validated('idempotency_key'), $request->validated('evidence'), $request));
    }
}
