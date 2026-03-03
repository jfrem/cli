<?php

declare(strict_types=1);

namespace PhpInit\Cli\Tests\Integration;

use PhpInit\Cli\Support\ProjectScaffolder;
use PHPUnit\Framework\TestCase;

final class ConnectionStringModeScaffoldTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php-init-dsn-it-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpRoot);
        parent::tearDown();
    }

    public function testConnectionStringModeStoresDsnAndSkipsDockerByDefault(): void
    {
        $projectPath = $this->tmpRoot . DIRECTORY_SEPARATOR . 'demo-dsn';
        $config = [
            'projectName' => 'demo-dsn',
            'preset' => 'api-auth-jwt',
            'dbMode' => 'connection-string',
            'dbType' => 'sqlsrv',
            'dbDsn' => 'sqlsrv:Server=db.example.com,1433;Database=audfact;Encrypt=yes;TrustServerCertificate=no',
            'dbHost' => 'ignored-host',
            'dbPort' => '1433',
            'dbName' => 'audfact',
            'dbUser' => 'sa',
            'dbPass' => 'secret',
            'appEnv' => 'development',
            'allowedOrigins' => '*',
            'jwtAccessExp' => 900,
            'jwtRefreshExp' => 2592000,
            'generateTests' => true,
            'withDocker' => false,
        ];

        (new ProjectScaffolder())->generate($projectPath, $config);

        $this->assertFileExists($projectPath . DIRECTORY_SEPARATOR . '.env');
        $this->assertFileDoesNotExist($projectPath . DIRECTORY_SEPARATOR . 'docker-compose.yml');

        $env = (string) file_get_contents($projectPath . DIRECTORY_SEPARATOR . '.env');
        $this->assertStringContainsString("DB_MODE=connection-string\n", $env);
        $this->assertStringContainsString("DB_DSN=sqlsrv:Server=db.example.com,1433;Database=audfact;Encrypt=yes;TrustServerCertificate=no\n", $env);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}


