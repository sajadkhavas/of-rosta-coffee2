<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class Ps10ReleaseRecoveryContractTest extends TestCase
{
    public function test_ps10_release_recovery_is_fail_closed_and_preserves_lineage(): void
    {
        $root = dirname(__DIR__, 3);
        $workflowPath = $root.'/.github/workflows/ps10-final-source-release.yml';
        $recoveryPath = $root.'/docs/pre-server/PS10_RELEASE_RECOVERY.md';

        $this->assertFileExists($workflowPath);
        $this->assertFileExists($recoveryPath);

        $workflow = file_get_contents($workflowPath);
        $recovery = file_get_contents($recoveryPath);

        $this->assertIsString($workflow);
        $this->assertIsString($recovery);

        $this->assertStringContainsString('rosta-pre-server-2026-09-01', $workflow);
        $this->assertStringContainsString('ab96dd2280f9e1870455cadc10297ee0fa20a308', $workflow);
        $this->assertStringContainsString('rosta-pre-server-2026-09-02', $workflow);
        $this->assertStringContainsString('fd55c99e80d8be3b130bde5ce172f5b3066a28bd', $workflow);
        $this->assertStringContainsString('9d466b787ac5b602d0417a6d124cbd438a12557d', $workflow);

        $this->assertStringContainsString('workspace-after-build.txt', $workflow);
        $this->assertStringContainsString("grep -vE '^ M src/routeTree\\.gen\\.ts$'", $workflow);
        $this->assertStringContainsString('Unexpected build workspace mutation', $workflow);
        $this->assertStringContainsString('git checkout -- src/routeTree.gen.ts', $workflow);
        $this->assertStringContainsString('test -z "$(git status --porcelain --untracked-files=all)"', $workflow);

        $this->assertStringContainsString('real_server_activated', $workflow);
        $this->assertStringContainsString('external_provider_activation_claimed', $workflow);
        $this->assertStringContainsString('false', $workflow);
        $this->assertStringContainsString('--draft', $workflow);
        $this->assertStringContainsString('--draft=false', $workflow);

        $this->assertStringContainsString('no PS10 tag was created', $recovery);
        $this->assertStringContainsString('full exact-head workflow matrix', $recovery);
    }
}
