<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HubWorkItemAction;
use App\Enums\HubWorkItemStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hub\AssignHubWorkItemRequest;
use App\Http\Requests\Hub\TransitionHubWorkItemRequest;
use App\Http\Resources\HubWorkItemResource;
use App\Models\HubWorkItem;
use App\Models\User;
use App\Services\Catalog\CatalogAccess;
use App\Services\Hub\RostaHubOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminHubOperationsController extends Controller
{
    public function index(Request $request, CatalogAccess $access, RostaHubOperationsService $operations): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);
        $operations->backfillMissing();
        $query = HubWorkItem::query()->with(['hub', 'assignedOperator:id,name', 'order:id,order_number', 'subOrder:id,roastery_id,status', 'orderItemService.grindingProfile', 'inboundShipmentLeg', 'outboundShipmentLeg', 'actions.actor:id,name'])->latest();
        $status = trim((string) $request->query('status', ''));
        if (in_array($status, array_column(HubWorkItemStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }
        $page = $query->paginate(max(1, min(100, (int) $request->query('per_page', 50))));

        return ApiResponse::success(['items' => HubWorkItemResource::collection($page->getCollection())->resolve($request), 'pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function operators(Request $request, CatalogAccess $access): JsonResponse
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);
        $operators = User::query()->whereHas('roleAssignments', static fn ($query) => $query->where('role', Role::HubOperator->value))->orderBy('name')->get(['id', 'name']);

        return ApiResponse::success(['items' => $operators->map(static fn (User $operator): array => ['id' => $operator->id, 'name' => $operator->name])->all()]);
    }

    public function assign(AssignHubWorkItemRequest $request, string $workItemId, CatalogAccess $access, RostaHubOperationsService $operations): HubWorkItemResource
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);

        return new HubWorkItemResource($operations->assign($user, $workItemId, (string) $request->validated('operator_id'), (string) $request->validated('idempotency_key'), $request->validated('note'), $request));
    }

    public function transition(TransitionHubWorkItemRequest $request, string $workItemId, CatalogAccess $access, RostaHubOperationsService $operations): HubWorkItemResource
    {
        /** @var User $user */ $user = $request->user();
        $access->assertAdministrator($user);

        return new HubWorkItemResource($operations->transition($user, $workItemId, HubWorkItemAction::from((string) $request->validated('action')), (string) $request->validated('idempotency_key'), $request->validated('evidence'), $request));
    }
}
