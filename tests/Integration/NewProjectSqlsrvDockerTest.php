<?php

declare(strict_types=1);

namespace PhpInit\Cli\Tests\Integration;

use PhpInit\Cli\Support\ProjectScaffolder;
use PHPUnit\Framework\TestCase;

final class NewProjectSqlsrvDockerTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php-init-it-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDir($this->tmpRoot);
        parent::tearDown();
    }

    public function testSqlsrvDockerScaffoldUsesSafeDefaults(): void
    {
        $projectPath = $this->tmpRoot . DIRECTORY_SEPARATOR . 'demo-auth';

        $config = [
            'projectName' => 'demo-auth',
            'preset' => 'api-auth-jwt',
            'dbMode' => 'docker',
            'dbType' => 'sqlsrv',
            'dbHost' => 'localhost',
            'dbPort' => '1433',
            'dbName' => 'app_db',
            'dbUser' => 'sa',
            'dbPass' => '',
            'appEnv' => 'development',
            'allowedOrigins' => '*',
            'jwtAccessExp' => 900,
            'jwtRefreshExp' => 2592000,
            'generateTests' => true,
            'withDocker' => true,
        ];

        (new ProjectScaffolder())->generate($projectPath, $config);

        $this->assertFileExists($projectPath . DIRECTORY_SEPARATOR . '.env');
        $this->assertFileExists($projectPath . DIRECTORY_SEPARATOR . 'docker-compose.yml');
        $this->assertFileExists($projectPath . DIRECTORY_SEPARATOR . '.env.example');

        $env = $this->readEnvFile($projectPath . DIRECTORY_SEPARATOR . '.env');
        $compose = (string) file_get_contents($projectPath . DIRECTORY_SEPARATOR . 'docker-compose.yml');
        $envExample = (string) file_get_contents($projectPath . DIRECTORY_SEPARATOR . '.env.example');

        $this->assertSame('db', $env['DB_HOST'] ?? null);
        $this->assertSame('app_db', $env['DB_NAME'] ?? null);
        $this->assertSame('docker', $env['DB_MODE'] ?? null);
        $this->assertSame('1', $env['DB_ENCRYPT'] ?? null);
        $this->assertSame('1', $env['DB_TRUST_SERVER_CERT'] ?? null);

        $dbPass = (string) ($env['DB_PASS'] ?? '');
        $this->assertNotSame('', $dbPass);
        $this->assertNotSame('YourStrong!Passw0rd', $dbPass);

        $this->assertStringContainsString('MSSQL_SA_PASSWORD: ${DB_PASS}', $compose);
        $this->assertStringNotContainsString('MSSQL_SA_PASSWORD: YourStrong!Passw0rd', $compose);

        $this->assertStringContainsString('JWT_SECRET=CHANGE_ME_IN_RUNTIME_SECRET_MANAGER', $envExample);
    }

    /**
     * @return array<string,string>
     */
    private function readEnvFile(string $path): array
    {
        $result = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $result[trim($k)] = trim($v);
        }

        return $result;
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

