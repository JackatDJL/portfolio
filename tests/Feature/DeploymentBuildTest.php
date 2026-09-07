<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentBuildTest extends TestCase
{
    public function test_the_deployment_build_does_not_fetch_publication_pdfs(): void
    {
        $buildScript = file_get_contents(base_path('build.sh'));

        $this->assertIsString($buildScript);
        $this->assertStringNotContainsString('sync-publication-previews.php', $buildScript);
    }
}
