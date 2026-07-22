<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesRecordedSession;
use Tests\TestCase;

final class InquiryWorkflowTest extends TestCase
{
    use AuthenticatesRecordedSession;
    use RefreshDatabase;

    public function test_contact_success_requires_persistence_and_duplicate_is_replayed(): void
    {
        $payload = [
            'type' => 'order_issue',
            'name' => 'سجاد خواص',
            'mobile' => '09123456789',
            'email' => '',
            'order_number' => 'R-TEST-1001',
            'message' => 'وضعیت ارسال سفارش من چند روز است تغییری نکرده است.',
            'website' => '',
        ];

        $first = $this->postJson('/api/v1/inquiries', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.replayed', false)
            ->json('data');
        $second = $this->postJson('/api/v1/inquiries', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.replayed', true)
            ->assertJsonPath('data.reference_id', $first['reference_id'])
            ->json('data');

        $this->assertSame($first['reference_id'], $second['reference_id']);
        $this->assertDatabaseCount('inquiries', 1);
        $inquiry = Inquiry::query()->firstOrFail();
        $this->assertSame('09123456789', $inquiry->mobile);
        $this->assertSame($payload['message'], $inquiry->message);
        $this->assertSame('new', $inquiry->status->value);
        $this->assertNotSame('127.0.0.1', $inquiry->ip_hmac);
        $this->assertSame(64, strlen($inquiry->ip_hmac));
        $this->assertNull($inquiry->email);
    }

    public function test_honeypot_is_rejected_without_persistence(): void
    {
        $this->postJson('/api/v1/inquiries', [
            'type' => 'support',
            'name' => 'ربات تست',
            'mobile' => '09123456789',
            'message' => 'این پیام به دلیل پر شدن فیلد مخفی نباید ثبت شود.',
            'website' => 'https://spam.example',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['website']);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_administrator_can_read_and_resolve_inquiry(): void
    {
        $reference = $this->postJson('/api/v1/inquiries', [
            'type' => 'privacy_request',
            'name' => 'کاربر حریم خصوصی',
            'email' => 'privacy@example.com',
            'message' => 'لطفاً درباره داده‌های ذخیره‌شده حساب من توضیح دهید.',
            'website' => '',
        ])->assertCreated()->json('data.reference_id');

        $administrator = User::factory()->create();
        $this->authenticateWithRole($administrator, Role::Administrator);
        $this->getJson('/api/v1/admin/inquiries?status=new')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $reference)
            ->assertJsonPath('data.items.0.email', 'privacy@example.com');
        $this->patchJson('/api/v1/admin/inquiries/'.$reference, [
            'status' => 'resolved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.assigned_to', $administrator->id);

        $this->assertNotNull(Inquiry::query()->findOrFail($reference)->resolved_at);
    }
}
