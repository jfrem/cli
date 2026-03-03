<?php

declare(strict_types=1);

namespace PhpInit\Cli\Tests\Integration;

use PhpInit\Cli\Command\DbFreshCommand;
use PhpInit\Cli\Command\DbMigrateCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DbCommandsSafetyTest extends TestCase
{
    private string $tmpRoot;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = getcwd() ?: '.';
        $this->tmpRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php-init-db-it-' . bin2hex(random_bytes(6));
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

    public function testDbFreshBlocksSystemDatabasesForSqlsrv(): void
    {
        file_put_contents(
            $this->tmpRoot . DIRECTORY_SEPARATOR . '.env',
            "DB_TYPE=sqlsrv\nDB_HOST=localhost\nDB_PORT=1433\nDB_NAME=master\nDB_USER=sa\nDB_PASS=secret\nDB_ENCRYPT=1\nDB_TRUST_SERVER_CERT=1\n"
        );

        $tester = new CommandTester(new DbFreshCommand());
        $exitCode = $tester->execute(['--force' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Operacion bloqueada', $tester->getDisplay());
    }

    public function testDbMigrateRejectsUnsafeDatabaseName(): void
    {
        file_put_contents(
            $this->tmpRoot . DIRECTORY_SEPARATOR . '.env',
            "DB_TYPE=sqlsrv\nDB_HOST=localhost\nDB_PORT=1433\nDB_NAME=bad-name\nDB_USER=sa\nDB_PASS=secret\nDB_ENCRYPT=1\nDB_TRUST_SERVER_CERT=1\n"
        );

        $tester = new CommandTester(new DbMigrateCommand());
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('DB_NAME invalido', $tester->getDisplay());
    }

    public function testDbFreshIsBlockedWhenUsingDbDsnMode(): void
    {
        file_put_contents(
            $this->tmpRoot . DIRECTORY_SEPARATOR . '.env',
            "DB_TYPE=sqlsrv\nDB_MODE=connection-string\nDB_DSN=sqlsrv:Server=demo,1433;Database=demo\nDB_USER=sa\nDB_PASS=secret\n"
        );

        $tester = new CommandTester(new DbFreshCommand());
        $exitCode = $tester->execute(['--force' => true]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('no esta permitido con DB_DSN', $tester->getDisplay());
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

