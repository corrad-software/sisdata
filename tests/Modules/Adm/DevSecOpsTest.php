<?php

namespace Tests\Modules\Adm;

use PHPUnit\Framework\TestCase;

/**
 * SRS-ADM-23/24 were delivered in F0 as the CI pipeline; these tests keep the
 * pipeline from silently losing its security stages. (DAST is not in CI yet.)
 */
class DevSecOpsTest extends TestCase
{
    private function ci(): string
    {
        $path = dirname(__DIR__, 3).'/.github/workflows/ci.yml';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_SRS_ADM_23_pipeline_runs_sast_sca_and_secret_scanning(): void
    {
        $ci = $this->ci();
        $this->assertStringContainsString('semgrep', $ci);          // SAST
        $this->assertStringContainsString('phpstan analyse', $ci);  // static analysis
        $this->assertStringContainsString('composer audit', $ci);   // SCA (PHP)
        $this->assertStringContainsString('npm audit', $ci);        // SCA (JS)
        $this->assertStringContainsString('gitleaks', $ci);         // secret detection
    }

    public function test_SRS_ADM_24_quality_gate_fails_the_build_on_high_or_critical_findings(): void
    {
        $ci = $this->ci();
        $this->assertMatchesRegularExpression('/npm audit --audit-level=(high|critical|moderate|low)/', $ci);
        $this->assertStringNotContainsString('continue-on-error: true', $ci);
    }
}
