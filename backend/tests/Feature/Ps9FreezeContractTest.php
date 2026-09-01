<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class Ps9FreezeContractTest extends TestCase
{
    public function test_final_freeze_artifacts_are_present_and_use_the_canonical_tag(): void
    {
        $root = dirname(__DIR__, 3);

        $requiredFiles = [
            '.github/workflows/ps9-final-freeze.yml',
            'docs/PRE_SERVER_ACCEPTANCE_STATUS.md',
            'docs/pre-server/FINAL_PRE_SERVER_ACCEPTANCE.md',
            'docs/pre-server/PS9_RELEASE_NOTES.md',
            'deploy/staging/rehearsal.sh',
            'deploy/production/infrastructure-audit.sh',
            'deploy/production/contract-test.sh',
            'deploy/production/rehearsal.sh',
        ];

        foreach ($requiredFiles as $relativePath) {
            $this->assertFileExists($root.'/'.$relativePath, "Missing PS9 freeze artifact: {$relativePath}");
        }

        $acceptance = file_get_contents($root.'/docs/pre-server/FINAL_PRE_SERVER_ACCEPTANCE.md');
        $releaseNotes = file_get_contents($root.'/docs/pre-server/PS9_RELEASE_NOTES.md');
        $workflow = file_get_contents($root.'/.github/workflows/ps9-final-freeze.yml');

        $this->assertIsString($acceptance);
        $this->assertIsString($releaseNotes);
        $this->assertIsString($workflow);

        foreach ([$acceptance, $releaseNotes, $workflow] as $document) {
            $this->assertStringContainsString('rosta-pre-server-2026-09-01', $document);
        }

        $this->assertStringContainsString('PRE-SERVER GO', $acceptance);
        $this->assertStringContainsString('real_server_activated', $workflow);
        $this->assertStringContainsString('false', $workflow);
    }

    public function test_freeze_keeps_current_zarinpal_production_hosts_and_no_historical_api_host(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/config/rosta.php');

        $this->assertIsString($config);
        $this->assertStringContainsString('https://payment.zarinpal.com/pg/v4/payment/request.json', $config);
        $this->assertStringContainsString('https://payment.zarinpal.com/pg/StartPay', $config);
        $this->assertStringContainsString('https://payment.zarinpal.com/pg/v4/payment/verify.json', $config);
        $this->assertStringNotContainsString('api.zarinpal.com', $config);
    }
}
