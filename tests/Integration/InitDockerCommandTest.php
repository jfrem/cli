<?php

declare(strict_types=1);

namespace AudFact\Cli\Tests\Integration;

use AudFact\Cli\Command\InitDockerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class InitDockerCommandTest extends TestCase
{
    private string $tmpRoot;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd() ?: '.';
        $this->tmpRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php-init-docker-it-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot, 0775, true);
        mkdir($this->tmpRoot . DIRECTORY_SEPARATOR . 'app', 0775, true);
        mkdir($this->tmpRoot . DIRECTORY_SEPARATOR . 'core', 0775, true);
        mkdir($this->tmpRoot . DIRECTORY_SEPARATOR . 'public', 0775, true);
        file_put_contents($this->tmpRoot . DIRECTORY_SEPARATOR . 'composer.json', "{}\n");
        chdir($this->tmpRoot);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->deleteDir($this->tmpRoot);
        parent::tearDown();
    }

    public function testInitDockerGeneratesSqlsrvStackFromEnv(): void
    {
        file_put_contents($this->tmpRoot . DIRECTORY_SEPARATOR . '.env', "DB_TYPE=sqlsrv\n");

        $tester = new CommandTester(new InitDockerCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->tmpRoot . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile');
        $this->assertFileExists($this->tmpRoot . DIRECTORY_SEPARATOR . 'docker-compose.yml');

        $dockerfile = (string) file_get_contents($this->tmpRoot . DIRECTORY_SEPARATOR . 'docker' . DIRECTORY_SEPARATOR . 'Dockerfile');
        $compose = (string) file_get_contents($this->tmpRoot . DIRECTORY_SEPARATOR . 'docker-compose.yml');

        $this->assertStringContainsString('sqlsrv', $dockerfile);
        $this->assertStringContainsString('mcr.microsoft.com/mssql/server:2022-latest', $compose);
        $this->assertStringContainsString('MSSQL_SA_PASSWORD: ${DB_PASS}', $compose);
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

