<?php

namespace App\Http\Requests\Carrier;

use App\Enums\ShipmentLegStatus;
use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ManageShipmentLegCarrierRequest extends FormRequest
{
    use RejectsUnexpectedInput;

    protected function prepareForValidation(): void
    {
        $this->rejectUnexpected([
            'carrier',
            'tracking_code',
            'status',
            'reason',
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier' => ['required', 'string', 'max:120'],
            'tracking_code' => ['required', 'string', 'max:200'],
            'status' => [
                'required',
                'string',
                Rule::in([
                    ShipmentLegStatus::AwaitingPickup->value,
                    ShipmentLegStatus::PickedUp->value,
                    ShipmentLegStatus::InTransit->value,
                    ShipmentLegStatus::PickupFailed->value,
                    ShipmentLegStatus::DeliveryFailed->value,
                    ShipmentLegStatus::Lost->value,
                    ShipmentLegStatus::Damaged->value,
                    ShipmentLegStatus::RequiresReview->value,
                ]),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
