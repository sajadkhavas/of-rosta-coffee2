<?php

namespace App\Http\Requests\Fulfillment;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Models\SubOrder;
use App\Services\Fulfillment\FreshnessDispatchGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFulfillmentRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'status',
            'carrier',
            'tracking_code',
            'internal_note',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in([
                    'preparing',
                    'ready_to_ship',
                    'shipped',
                    'delivered',
                ]),
            ],
            'carrier' => ['nullable', 'string', 'max:120', 'required_if:status,shipped'],
            'tracking_code' => [
                'nullable',
                'string',
                'min:3',
                'max:200',
                'regex:/^[A-Za-z0-9\x{0600}-\x{06FF}._\-\/ ]+$/u',
                'required_if:status,shipped',
            ],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->validated('status') !== 'shipped') {
            return;
        }

        $orderId = (string) $this->route('orderId');
        $roasteryId = $this->route('roasteryId');

        $query = SubOrder::query()->where('order_id', $orderId);
        if (is_string($roasteryId) && $roasteryId !== '') {
            $query->where('roastery_id', $roasteryId);
        }

        $subOrder = $query->orderBy('created_at')->firstOrFail();
        app(FreshnessDispatchGuard::class)->assertDispatchable($subOrder);
    }
}
