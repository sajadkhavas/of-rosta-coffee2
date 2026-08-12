<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\ReconciliationStatus;
use App\Enums\RefundStatus;
use App\Models\FinancialReconciliationCase;
use App\Models\NotificationOutbox;
use App\Models\PaymentAttempt;
use App\Models\RefundAttempt;
use App\Services\Media\SecureImageProcessor;
use App\Services\Notifications\SmsProviderManager;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Refunds\RefundProviderManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class BackendReadiness extends Command
{
    protected $signature = 'rosta:readiness {--json : Print JSON only} {--strict : Treat operational warnings as failures}';

    protected $description = 'Validate Rosta backend configuration, dependencies and infrastructure readiness';

    public function handle(
        PaymentProviderManager $payments,
        RefundProviderManager $refunds,
        SmsProviderManager $sms,
        SecureImageProcessor $images,
    ): int {
        $checks = [];
        $warnings = [];

        $this->check($checks, 'composer_lock', is_file(base_path('composer.lock')), 'backend/composer.lock is committed');
        $this->check($checks, 'app_key', trim((string) config('app.key')) !== '', 'APP_KEY is configured');
        $this->check(
            $checks,
            'debug_disabled',
            app()->environment(['local', 'testing']) || config('app.debug') === false,
            'APP_DEBUG is false outside local/testing',
        );

        try {
            DB::select('select 1');
            $this->check($checks, 'database', true, 'Database connection succeeds');
        } catch (Throwable) {
            $this->check($checks, 'database', false, 'Database connection succeeds');
        }

        try {
            $pong = Redis::connection()->command('ping');
            $this->check(
                $checks,
                'redis',
                in_array(strtoupper((string) $pong), ['PONG', '1'], true),
                'Redis responds to PING',
            );
        } catch (Throwable) {
            $this->check($checks, 'redis', false, 'Redis responds to PING');
        }

        $requiredTables = [
            'users',
            'roasteries',
            'products',
            'product_variants',
            'roast_batches',
            'orders',
            'sub_orders',
            'inventory_reservations',
            'payment_attempts',
            'refund_attempts',
            'financial_reconciliation_cases',
            'notification_outbox',
            'shipments',
            'reviews',
            'inquiries',
            'media_upload_intents',
        ];
        $missingTables = [];
        foreach ($requiredTables as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            } catch (Throwable) {
                $missingTables[] = $table;
            }
        }
        $this->check(
            $checks,
            'schema_current',
            $missingTables === [],
            $missingTables === []
                ? 'Required commerce tables exist'
                : 'Missing tables: '.implode(', ', $missingTables),
        );

        $paymentEnabled = (bool) config('rosta.payment.enabled', false);
        $this->check(
            $checks,
            'payment_activation',
            ! $paymentEnabled || $payments->ready(),
            $paymentEnabled
                ? 'Enabled payment provider is configured'
                : 'Payment is intentionally disabled',
        );
        $refundEnabled = (bool) config('rosta.refund.enabled', false);
        $this->check(
            $checks,
            'refund_activation',
            ! $refundEnabled || $refunds->ready(),
            $refundEnabled
                ? 'Enabled refund provider is configured'
                : 'Refund execution is intentionally disabled',
        );
        $smsEnabled = (bool) config('rosta.notifications.enabled', false);
        $this->check(
            $checks,
            'sms_activation',
            ! $smsEnabled || $sms->ready(),
            $smsEnabled
                ? 'Enabled order SMS provider is configured'
                : 'Order SMS is intentionally disabled',
        );
        $mediaEnabled = (bool) config('rosta.media_uploads.enabled', false);
        $mediaBaseUrl = (string) config('rosta.media_uploads.public_base_url');
        $mediaReady = trim($mediaBaseUrl) !== ''
            && parse_url($mediaBaseUrl, PHP_URL_SCHEME) === 'https'
            && trim((string) config('rosta.media_uploads.disk')) !== ''
            && config('rosta.media_uploads.malware_policy') === 'decode_reencode'
            && $images->isAvailable();
        $this->check(
            $checks,
            'media_activation',
            ! $mediaEnabled || $mediaReady,
            $mediaEnabled
                ? 'Enabled media pipeline has storage, HTTPS CDN and JPEG/WebP/AVIF decoding'
                : 'Media uploads are intentionally disabled',
        );

        if ($checks['database']['passed'] && Schema::hasTable('payment_attempts')) {
            $reviewCount = PaymentAttempt::query()
                ->where('status', PaymentAttemptStatus::RequiresReview->value)
                ->count();
            if ($reviewCount > 0) {
                $warnings[] = [
                    'code' => 'payments_require_review',
                    'count' => $reviewCount,
                    'message' => 'Payment attempts require financial reconciliation.',
                ];
            }
        }
        if ($checks['database']['passed'] && Schema::hasTable('refund_attempts')) {
            $refundReviewCount = RefundAttempt::query()
                ->whereIn('status', [RefundStatus::Failed->value, RefundStatus::RequiresReview->value])
                ->count();
            if ($refundReviewCount > 0) {
                $warnings[] = [
                    'code' => 'refunds_require_review',
                    'count' => $refundReviewCount,
                    'message' => 'Refund attempts failed or have an unknown provider outcome.',
                ];
            }
        }
        if ($checks['database']['passed'] && Schema::hasTable('financial_reconciliation_cases')) {
            $openCases = FinancialReconciliationCase::query()
                ->whereIn('status', [ReconciliationStatus::Open->value, ReconciliationStatus::Investigating->value])
                ->count();
            if ($openCases > 0) {
                $warnings[] = [
                    'code' => 'financial_reconciliation_open',
                    'count' => $openCases,
                    'message' => 'Financial reconciliation cases remain open.',
                ];
            }
        }
        if ($checks['database']['passed'] && Schema::hasTable('notification_outbox')) {
            $failedNotifications = NotificationOutbox::query()
                ->where('status', NotificationStatus::Failed->value)
                ->count();
            if ($failedNotifications > 0) {
                $warnings[] = [
                    'code' => 'notifications_failed',
                    'count' => $failedNotifications,
                    'message' => 'Notification outbox records exhausted retries.',
                ];
            }
        }

        $failed = array_values(array_filter(
            $checks,
            static fn (array $item): bool => ! $item['passed'],
        ));
        $strictWarningFailure = (bool) $this->option('strict') && $warnings !== [];
        $report = [
            'ready' => $failed === [] && ! $strictWarningFailure,
            'environment' => app()->environment(),
            'contract_version' => config('rosta.contract_version'),
            'generated_at' => gmdate(DATE_ATOM),
            'checks' => $checks,
            'warnings' => $warnings,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                $report,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            foreach ($checks as $name => $item) {
                $this->line(sprintf(
                    '[%s] %s — %s',
                    $item['passed'] ? 'PASS' : 'FAIL',
                    $name,
                    $item['evidence'],
                ));
            }
            foreach ($warnings as $warning) {
                $this->warn(sprintf(
                    '[WARN] %s (%d) — %s',
                    $warning['code'],
                    $warning['count'],
                    $warning['message'],
                ));
            }
        }

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array{passed: bool, evidence: string}>  $checks
     */
    private function check(
        array &$checks,
        string $name,
        bool $passed,
        string $evidence,
    ): void {
        $checks[$name] = compact('passed', 'evidence');
    }
}
