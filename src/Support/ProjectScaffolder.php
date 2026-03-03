<?php

declare(strict_types=1);

namespace AudFact\Cli\Support;

final class ProjectScaffolder
{
    /**
     * @param array<string,mixed> $config
     */
    public function generate(string $basePath, array $config): void
    {
        $preset = (string) ($config['preset'] ?? 'api-basic');
        $withJwt = $preset !== 'api-basic';
        $withDocker = (bool) ($config['withDocker'] ?? false);
        $generateTests = (bool) ($config['generateTests'] ?? true);
        $dbType = (string) ($config['dbType'] ?? 'mysql');

        $envConfig = $this->resolveEnvConfig($config, $withDocker, $dbType);

        $dirs = [
            'app/Controllers',
            'app/Models',
            'app/Routes',
            'core/Exceptions',
            'public',
            'logs',
            'database/migrations/' . $dbType,
        ];

        if ($generateTests) {
            $dirs[] = 'tests/Unit';
            $dirs[] = 'tests/Integration';
        }

        if ($withJwt) {
            $dirs[] = 'app/Middleware';
            $dirs[] = 'app/Services';
        }

        if ($withDocker) {
            $dirs[] = 'docker';
        }

        foreach ($dirs as $dir) {
            $full = $basePath . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($full) && !mkdir($full, 0775, true) && !is_dir($full)) {
                throw new \RuntimeException("No se pudo crear directorio: {$dir}");
            }
        }

        $files = [
            'composer.json' => ScaffoldTemplates::projectComposer($withJwt),
            '.gitignore' => ScaffoldTemplates::gitignore(),
            '.env' => ScaffoldTemplates::env($envConfig, (string) ($envConfig['appEnv'] ?? 'development'), $withJwt, false),
            '.env.example' => ScaffoldTemplates::env($envConfig, 'development', $withJwt, true),
            '.env.dev' => ScaffoldTemplates::env($envConfig, 'development', $withJwt, false),
            '.env.test' => ScaffoldTemplates::env($envConfig, 'testing', $withJwt, true),
            '.env.prod' => ScaffoldTemplates::env($envConfig, 'production', $withJwt, true),
            'README.md' => ScaffoldTemplates::readme((string) $config['projectName'], $preset, $withDocker, $dbType),
            'public/index.php' => ScaffoldTemplates::publicIndex(),
            'public/.htaccess' => ScaffoldTemplates::htaccess(),
            'app/Controllers/Controller.php' => ScaffoldTemplates::baseController(),
            'app/Controllers/HealthController.php' => ScaffoldTemplates::healthController(),
            'app/Models/Model.php' => ScaffoldTemplates::baseModel(),
            'app/Routes/web.php' => ScaffoldTemplates::webRoutes($withJwt),
            'core/Env.php' => ScaffoldTemplates::coreEnv(),
            'core/Database.php' => ScaffoldTemplates::coreDatabase($dbType),
            'core/Route.php' => ScaffoldTemplates::coreRoute(),
            'core/Router.php' => ScaffoldTemplates::coreRouter(),
            'core/Response.php' => ScaffoldTemplates::coreResponse(),
            'core/Validator.php' => ScaffoldTemplates::coreValidator(),
            'core/Logger.php' => ScaffoldTemplates::coreLogger(),
            'core/RateLimit.php' => ScaffoldTemplates::coreRateLimit(),
            'core/Middleware.php' => ScaffoldTemplates::coreMiddleware(),
            'core/Exceptions/HttpResponseException.php' => ScaffoldTemplates::httpResponseException(),
        ];

        if ($generateTests) {
            $files['phpunit.xml'] = ScaffoldTemplates::phpunitXml();
            $files['tests/Integration/HealthCheckTest.php'] = ScaffoldTemplates::healthTest();
        }

        if ($withJwt) {
            $files['core/JWT.php'] = ScaffoldTemplates::coreJwt();
            $files['app/Middleware/AuthMiddleware.php'] = ScaffoldTemplates::authMiddleware();
            $files['app/Controllers/AuthController.php'] = ScaffoldTemplates::authController();
            $files['app/Services/AuthService.php'] = ScaffoldTemplates::authService();
            $files['app/Models/UserModel.php'] = ScaffoldTemplates::userModel();
            $files['app/Models/RefreshTokenModel.php'] = ScaffoldTemplates::refreshTokenModel();
            $files['app/Models/JwtDenylistModel.php'] = ScaffoldTemplates::jwtDenylistModel();
            $files['database/migrations/' . $dbType . '/users.sql'] = ScaffoldTemplates::migrationUsers($dbType);
            $files['database/migrations/' . $dbType . '/refresh_tokens.sql'] = ScaffoldTemplates::migrationRefreshTokens($dbType);
            $files['database/migrations/' . $dbType . '/jwt_denylist.sql'] = ScaffoldTemplates::migrationDenylist($dbType);
        }

        if ($withDocker) {
            $files['docker/Dockerfile'] = ScaffoldTemplates::dockerfile($dbType);
            $files['docker/nginx.conf'] = ScaffoldTemplates::nginxConf();
            $files['docker/healthcheck.php'] = ScaffoldTemplates::dockerHealthcheck();
            $files['docker-compose.yml'] = ScaffoldTemplates::dockerCompose($dbType);
        }

        foreach ($files as $path => $content) {
            SafeWriter::write($basePath, $path, $content);
        }
    }

    /**
     * @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    private function resolveEnvConfig(array $config, bool $withDocker, string $dbType): array
    {
        if (!$withDocker) {
            return $config;
        }

        $resolved = $config;
        $dbHost = (string) ($resolved['dbHost'] ?? 'localhost');
        $dbPass = (string) ($resolved['dbPass'] ?? '');

        if ($dbHost === '' || $dbHost === 'localhost' || $dbHost === '127.0.0.1') {
            $resolved['dbHost'] = 'db';
        }

        if ($dbPass === '') {
            $resolved['dbPass'] = $this->generateStrongPassword();
        }

        if ($dbType === 'sqlsrv') {
            $resolved['dbEncrypt'] = '1';
            $resolved['dbTrustCert'] = '1';
            if ((string) ($resolved['dbName'] ?? '') === '') {
                $resolved['dbName'] = 'app_db';
            }
        }

        return $resolved;
    }

    private function generateStrongPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*_+-=';
        $length = strlen($alphabet);
        $bytes = random_bytes(18);
        $password = '';

        for ($i = 0; $i < 18; $i++) {
            $password .= $alphabet[ord($bytes[$i]) % $length];
        }

        return $password;
    }
}

