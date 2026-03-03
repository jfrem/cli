<?php

declare(strict_types=1);

namespace AudFact\Cli\Support;

final class ScaffoldTemplates
{
    public static function projectComposer(bool $withJwt): string
    {
        $jwt = $withJwt ? ",\n    \"firebase/php-jwt\": \"^7.0\"" : '';

        return <<<JSON
{
  "name": "usuario/php-backend",
  "description": "Backend PHP MVC con API REST JSON",
  "autoload": {
    "psr-4": {
      "App\\\\": "app/",
      "Core\\\\": "core/"
    }
  },
  "require": {
    "php": "^8.2",
    "ext-pdo": "*"{$jwt}
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5"
  }
}
JSON;
    }

    public static function gitignore(): string
    {
        return <<<TXT
/vendor/
/.env
/.env.*
!/.env.example
/logs/*.log
/.idea/
/.vscode/
TXT;
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function env(array $config, string $env, bool $withJwt, bool $placeholderSecrets = false): string
    {
        $dbType = (string) ($config['dbType'] ?? 'mysql');
        $dbHost = (string) ($config['dbHost'] ?? 'localhost');
        $dbPort = (string) ($config['dbPort'] ?? '3306');
        $dbName = (string) ($config['dbName'] ?? 'app_db');
        $dbUser = (string) ($config['dbUser'] ?? 'root');
        $dbPass = (string) ($config['dbPass'] ?? '');
        $allowedOrigins = (string) ($config['allowedOrigins'] ?? ($env === 'development' ? '*' : ''));
        if ($env !== 'development' && $allowedOrigins === '*') {
            $allowedOrigins = '';
        }
        $jwtAccessExp = (int) ($config['jwtAccessExp'] ?? 900);
        $jwtRefreshExp = (int) ($config['jwtRefreshExp'] ?? 2592000);
        $jwtSecret = $placeholderSecrets ? 'CHANGE_ME_IN_RUNTIME_SECRET_MANAGER' : bin2hex(random_bytes(32));
        $dbEncrypt = (string) ($config['dbEncrypt'] ?? ($dbType === 'sqlsrv' ? '1' : ''));
        $dbTrustCert = (string) ($config['dbTrustCert'] ?? ($dbType === 'sqlsrv' ? '0' : ''));

        $jwtConfig = '';
        if ($withJwt) {
            $jwtConfig = "\nJWT_SECRET={$jwtSecret}\nJWT_ACCESS_TOKEN_EXPIRATION={$jwtAccessExp}\nJWT_REFRESH_TOKEN_EXPIRATION={$jwtRefreshExp}";
        }

        $dbTlsConfig = '';
        if ($dbType === 'sqlsrv') {
            $dbTlsConfig = "\nDB_ENCRYPT={$dbEncrypt}\nDB_TRUST_SERVER_CERT={$dbTrustCert}";
        }

        return <<<ENV
APP_ENV={$env}
ALLOWED_ORIGINS={$allowedOrigins}
MAX_JSON_SIZE=1048576
REQUEST_TIMEOUT_MS=60000
RATE_LIMIT_MAX_REQUESTS=100
RATE_LIMIT_WINDOW_SECONDS=60
LOG_LEVEL=info
LOG_RETENTION_DAYS=7
LOG_MAX_SIZE_MB=10

DB_TYPE={$dbType}
DB_HOST={$dbHost}
DB_PORT={$dbPort}
DB_NAME={$dbName}
DB_USER={$dbUser}
DB_PASS={$dbPass}{$dbTlsConfig}{$jwtConfig}
ENV;
    }
    public static function readme(string $projectName, string $preset, bool $withDocker, string $dbType = 'mysql'): string
    {
        $runInstructions = $withDocker
            ? "- `docker compose up -d --build`\n- `docker compose logs -f nginx`\n- `docker compose logs -f php`"
            : "- `php -S localhost:8000 -t public`";

        $sqlsrvNote = '';
        if ($withDocker && $dbType === 'sqlsrv') {
            $sqlsrvNote = "\n\n## SQL Server\n\nSi la base no existe aun, inicializala desde la raiz del proyecto con:\n\n- `php-init db:fresh --force`\n\nEso crea la base configurada en `.env` y aplica migraciones del scaffold.";
        }

        return <<<MD
# {$projectName}

Proyecto generado con php-init ({$preset}).

## Comandos

- `composer install`
{$runInstructions}

## Health

- `GET http://localhost:8080/health` (Docker)
- `GET http://localhost:8000/health` (servidor embebido){$sqlsrvNote}
MD;
    }

    public static function publicIndex(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Env;
use Core\Logger;
use Core\Middleware;
use Core\RateLimit;
use Core\Response;
use Core\Router;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Permitted-Cross-Domain-Policies: none');
header('Referrer-Policy: no-referrer');

Env::load();

$requestTimeoutMs = (int) Env::get('REQUEST_TIMEOUT_MS', '60000');
if ($requestTimeoutMs > 0) {
    set_time_limit((int) ceil($requestTimeoutMs / 1000));
}

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof Core\Exceptions\HttpResponseException) {
        Response::json($e->getData(), $e->getCode());
        return;
    }

    Logger::error('Unhandled exception: ' . $e->getMessage(), ['exception' => $e]);
    $message = Env::get('APP_ENV', 'development') === 'production' ? 'Internal server error' : $e->getMessage();
    Response::json(['success' => false, 'message' => $message], 500);
});

$appEnv = Env::get('APP_ENV', 'development');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($appEnv === 'development') {
    header('Access-Control-Allow-Origin: *');
} else {
    $allowedOrigins = array_values(array_filter(array_map('trim', explode(',', Env::get('ALLOWED_ORIGINS', '')))));
    header('Vary: Origin');
    if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } elseif ($origin !== '') {
        Response::error('Origen no permitido por CORS', 403);
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    RateLimit::check(
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        (int) Env::get('RATE_LIMIT_MAX_REQUESTS', '100'),
        (int) Env::get('RATE_LIMIT_WINDOW_SECONDS', '60')
    );
    Middleware::register('auth', 'App\\Middleware\\AuthMiddleware::handle');

    $router = new Router();
    require __DIR__ . '/../app/Routes/web.php';
    $router->dispatch();
} catch (Core\Exceptions\HttpResponseException $e) {
    throw $e;
} catch (Throwable $e) {
    Logger::error('Application bootstrap failed: ' . $e->getMessage());
    Response::error('Application error', 500);
}
PHP;
    }

    public static function htaccess(): string
    {
        return <<<HT
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
HT;
    }

    public static function baseController(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Response;
use Core\Validator;

class Controller
{
    protected function getBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') === false) {
            Response::error('Content-Type must be application/json', 415);
        }

        $maxSize = (int) ($_ENV['MAX_JSON_SIZE'] ?? getenv('MAX_JSON_SIZE') ?: 1048576);
        $raw = (string) file_get_contents('php://input');

        if ($maxSize > 0 && strlen($raw) > $maxSize) {
            Response::error('Payload Too Large', 413);
        }

        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error('Invalid JSON payload', 400);
        }

        return is_array($data) ? $data : [];
    }

    protected function validate(array $rules): array
    {
        $data = $this->getBody();
        $errors = Validator::validate($data, $rules);
        if ($errors !== []) {
            Response::error('Errores de validacion', 422, $errors);
        }
        return $data;
    }

    protected function validateQuery(array $rules): array
    {
        $data = $_GET;
        $errors = Validator::validate($data, $rules);
        if ($errors !== []) {
            Response::error('Errores de validacion', 422, $errors);
        }
        return $data;
    }
}
PHP;
    }

    public static function healthController(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Database;
use Core\Response;

class HealthController extends Controller
{
    public function status(): void
    {
        $dbOk = false;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT 1');
            $dbOk = (bool) $stmt;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        Response::success([
            'status' => 'ok',
            'database' => $dbOk ? 'up' : 'down'
        ], 'Health check');
    }
}
PHP;
    }

    public static function baseModel(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database;
use PDO;

class Model
{
    protected PDO $db;
    protected string $table = '';
    protected array $fillable = [];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            throw new \RuntimeException('Security: $fillable must be defined in ' . static::class);
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}
PHP;
    }

    public static function webRoutes(bool $withJwt): string
    {
        $auth = $withJwt ? <<<'PHP'
$router->post('/auth/register', 'AuthController', 'register');
$router->post('/auth/login', 'AuthController', 'login');
$router->post('/auth/refresh', 'AuthController', 'refresh');
$router->post('/auth/logout', 'AuthController', 'logout')->middleware('auth');
$router->get('/auth/me', 'AuthController', 'me')->middleware('auth');
PHP : '';

        return "<?php\n\n\$router->get('/', 'Controller', 'index');\n\$router->get('/health', 'HealthController', 'status');\n" . $auth;
    }

    public static function coreEnv(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class Env
{
    public static function load(?string $path = null): void
    {
        $file = $path ?: dirname(__DIR__) . '/.env';
        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}
PHP;
    }

    public static function coreDatabase(string $dbType): string
    {
        $portDefault = $dbType === 'sqlsrv' ? '1433' : '3306';
        $userDefault = $dbType === 'sqlsrv' ? 'sa' : 'root';
        $dsn = $dbType === 'sqlsrv'
            ? 'sqlsrv:Server={$host},{$port};Database={$name};Encrypt={$encrypt};TrustServerCertificate={$trustServerCertificate}'
            : 'mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4';

        $template = <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '__PORT_DEFAULT__');
        $name = Env::get('DB_NAME', 'app_db');
        $user = Env::get('DB_USER', '__USER_DEFAULT__');
        $pass = Env::get('DB_PASS', '');
        $encrypt = Env::get('DB_ENCRYPT', '1');
        $trustServerCertificate = Env::get('DB_TRUST_SERVER_CERT', '0');
        $dsn = "__DSN__";

        self::$connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
PHP;

        return str_replace(
            ['__PORT_DEFAULT__', '__USER_DEFAULT__', '__DSN__'],
            [$portDefault, $userDefault, $dsn],
            $template
        );
    }

    public static function coreRoute(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class Route
{
    public function __construct(
        private string $method,
        private string $path,
        private string $controller,
        private string $action,
        private array $middlewares = []
    ) {
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }
    public function controller(): string { return $this->controller; }
    public function action(): string { return $this->action; }
    public function middlewares(): array { return $this->middlewares; }

    public function middleware(string ...$names): self
    {
        $this->middlewares = array_merge($this->middlewares, $names);
        return $this;
    }
}
PHP;
    }

    public static function coreRouter(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpResponseException;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function get(string $path, string $controller, string $action): Route { return $this->add('GET', $path, $controller, $action); }
    public function post(string $path, string $controller, string $action): Route { return $this->add('POST', $path, $controller, $action); }
    public function put(string $path, string $controller, string $action): Route { return $this->add('PUT', $path, $controller, $action); }
    public function delete(string $path, string $controller, string $action): Route { return $this->add('DELETE', $path, $controller, $action); }

    private function add(string $method, string $path, string $controller, string $action): Route
    {
        $route = new Route($method, $path, $controller, $action);
        $this->routes[] = $route;
        return $route;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            $params = [];
            if ($route->method() !== $method || !$this->match($route->path(), $uri, $params)) {
                continue;
            }

            foreach ($route->middlewares() as $mw) {
                Middleware::handle($mw);
            }

            $fqcn = 'App\\Controllers\\' . $route->controller();
            if (!class_exists($fqcn)) {
                throw new HttpResponseException(['success' => false, 'message' => 'Controller not found'], 500);
            }

            $instance = new $fqcn();
            $action = $route->action();
            if (!method_exists($instance, $action)) {
                throw new HttpResponseException(['success' => false, 'message' => 'Action not found'], 500);
            }

            call_user_func_array([$instance, $action], array_values($params));
            return;
        }

        throw new HttpResponseException(['success' => false, 'message' => 'Not found'], 404);
    }

    private function match(string $routePath, string $uri, array &$params): bool
    {
        $pattern = preg_replace('/\{([A-Za-z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        if (!preg_match($pattern, $uri, $matches)) {
            return false;
        }

        foreach ($matches as $k => $v) {
            if (is_string($k)) {
                $params[$k] = $v;
            }
        }

        return true;
    }
}
PHP;
    }

    public static function coreResponse(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

use Core\Exceptions\HttpResponseException;

class Response
{
    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function success(mixed $data = [], string $message = 'Operacion exitosa', int $code = 200): void
    {
        throw new HttpResponseException([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void
    {
        $res = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $res['errors'] = $errors;
        }
        throw new HttpResponseException($res, $code);
    }
}
PHP;
    }

    public static function coreValidator(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $tokens = explode('|', (string) $ruleSet);
            $exists = array_key_exists($field, $data);
            $value = $data[$field] ?? null;
            $isEmpty = !$exists || $value === null || $value === '';

            if (in_array('nullable', $tokens, true) && $isEmpty) {
                continue;
            }

            foreach ($tokens as $rule) {
                if ($rule === 'nullable') {
                    continue;
                }

                if ($rule === 'required' && $isEmpty) {
                    $errors[$field][] = "El campo {$field} es requerido";
                    continue;
                }

                if ($isEmpty) {
                    continue;
                }

                if ($rule === 'string' && !is_string($value)) {
                    $errors[$field][] = "El campo {$field} debe ser texto";
                    continue;
                }

                if ($rule === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[$field][] = "El campo {$field} debe ser un email valido";
                    continue;
                }

                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = "El campo {$field} debe ser numerico";
                    continue;
                }

                if ($rule === 'integer' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[$field][] = "El campo {$field} debe ser un entero";
                    continue;
                }

                if ($rule === 'boolean') {
                    $normalized = is_bool($value) ? $value : strtolower((string) $value);
                    $valid = in_array($normalized, [true, false, '1', '0', 1, 0, 'true', 'false'], true);
                    if (!$valid) {
                        $errors[$field][] = "El campo {$field} debe ser booleano";
                    }
                    continue;
                }

                if ($rule === 'alpha' && (!is_string($value) || !ctype_alpha($value))) {
                    $errors[$field][] = "El campo {$field} solo debe contener letras";
                    continue;
                }

                if ($rule === 'date') {
                    $str = (string) $value;
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
                        $errors[$field][] = "El campo {$field} debe tener formato YYYY-MM-DD";
                        continue;
                    }

                    [$year, $month, $day] = array_map('intval', explode('-', $str));
                    if (!checkdate($month, $day, $year)) {
                        $errors[$field][] = "El campo {$field} debe ser una fecha valida";
                    }
                    continue;
                }

                if (str_starts_with($rule, 'in:')) {
                    $allowed = array_map('trim', explode(',', substr($rule, 3)));
                    if (!in_array((string) $value, $allowed, true)) {
                        $errors[$field][] = "El campo {$field} debe ser uno de: " . implode(', ', $allowed);
                    }
                    continue;
                }

                if (str_starts_with($rule, 'min:') || str_starts_with($rule, 'min_length:')) {
                    $min = (int) (str_starts_with($rule, 'min:') ? substr($rule, 4) : substr($rule, 11));
                    if (strlen((string) $value) < $min) {
                        $errors[$field][] = "El campo {$field} debe tener al menos {$min} caracteres";
                    }
                    continue;
                }

                if (str_starts_with($rule, 'max:') || str_starts_with($rule, 'max_length:')) {
                    $max = (int) (str_starts_with($rule, 'max:') ? substr($rule, 4) : substr($rule, 11));
                    if (strlen((string) $value) > $max) {
                        $errors[$field][] = "El campo {$field} no puede tener mas de {$max} caracteres";
                    }
                    continue;
                }

                if (str_starts_with($rule, 'min_value:')) {
                    $min = (float) substr($rule, 10);
                    if (!is_numeric($value) || (float) $value < $min) {
                        $errors[$field][] = "El campo {$field} debe ser mayor o igual a {$min}";
                    }
                    continue;
                }

                if (str_starts_with($rule, 'max_value:')) {
                    $max = (float) substr($rule, 10);
                    if (!is_numeric($value) || (float) $value > $max) {
                        $errors[$field][] = "El campo {$field} debe ser menor o igual a {$max}";
                    }
                    continue;
                }
            }
        }

        return $errors;
    }
}
PHP;
    }

    public static function coreLogger(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class Logger
{
    private static string $logDir = __DIR__ . '/../logs';
    private static int $retentionDays = 7;
    private static int $maxSizeMb = 10;
    private static string $level = 'info';
    private const LEVELS = ['error' => 0, 'warning' => 1, 'info' => 2];

    public static function info(string $message, array $context = []): void { self::write('INFO', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::write('WARNING', $message, $context); }
    public static function error(string $message, array $context = []): void { self::write('ERROR', $message, $context); }

    private static function boot(): void
    {
        self::$level = strtolower((string) Env::get('LOG_LEVEL', 'info'));
        self::$retentionDays = (int) Env::get('LOG_RETENTION_DAYS', '7');
        self::$maxSizeMb = (int) Env::get('LOG_MAX_SIZE_MB', '10');
    }

    private static function write(string $level, string $message, array $context): void
    {
        self::boot();
        if (!self::shouldLog($level)) {
            return;
        }

        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0775, true);
        }

        self::cleanupOldFiles();

        $hostname = gethostname() ?: 'local';
        $file = self::$logDir . '/app-' . $hostname . '-' . date('Y-m-d') . '.log';
        self::rotateIfNeeded($file);

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => self::sanitize($context),
        ];

        file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function shouldLog(string $level): bool
    {
        $wanted = self::LEVELS[strtolower(self::$level)] ?? self::LEVELS['info'];
        $current = self::LEVELS[strtolower($level)] ?? self::LEVELS['info'];
        return $current <= $wanted;
    }

    private static function cleanupOldFiles(): void
    {
        if (self::$retentionDays <= 0 || !is_dir(self::$logDir)) {
            return;
        }

        $threshold = time() - (self::$retentionDays * 86400);
        $files = glob(self::$logDir . '/app-*.log*') ?: [];
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($file);
            }
        }
    }

    private static function rotateIfNeeded(string $file): void
    {
        if (!file_exists($file) || self::$maxSizeMb <= 0) {
            return;
        }

        $maxBytes = self::$maxSizeMb * 1024 * 1024;
        $size = filesize($file);
        if ($size === false || $size < $maxBytes) {
            return;
        }

        for ($i = 4; $i >= 1; $i--) {
            $from = $file . '.' . $i;
            $to = $file . '.' . ($i + 1);
            if (file_exists($from)) {
                @rename($from, $to);
            }
        }

        @rename($file, $file . '.1');
    }

    private static function sanitize(array $context): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'authorization', 'credit_card', 'ssn'];
        foreach ($context as $k => $v) {
            if (in_array(strtolower((string) $k), $sensitive, true)) {
                $context[$k] = '[REDACTED]';
            } elseif ($v instanceof \Throwable) {
                $context[$k] = [
                    'class' => get_class($v),
                    'message' => $v->getMessage(),
                    'file' => $v->getFile() . ':' . $v->getLine(),
                ];
            } elseif (is_array($v)) {
                $context[$k] = self::sanitize($v);
            }
        }
        return $context;
    }
}
PHP;
    }

    public static function coreRateLimit(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class RateLimit
{
    private static string $storageFile = __DIR__ . '/../logs/ratelimit.json';
    private static string $lockFile = __DIR__ . '/../logs/ratelimit.lock';
    private static int $cleanupEvery = 50;

    public static function check(string $ip, int $limit = 100, int $window = 60): bool
    {
        if ($limit < 1 || $window < 1) {
            return true;
        }

        try {
            if (function_exists('apcu_inc')) {
                return self::apcuCheck($ip, $limit, $window);
            }
        } catch (\Throwable $e) {
            Logger::warning('RateLimit APCu fallback activado', ['error' => $e->getMessage()]);
        }

        return self::fileCheck($ip, $limit, $window);
    }

    private static function apcuCheck(string $ip, int $limit, int $window): bool
    {
        $key = 'rl:' . sha1($ip);
        $current = apcu_inc($key);
        if ($current === false) {
            apcu_store($key, 1, $window);
            return true;
        }
        if ((int) $current > $limit) {
            Logger::warning("Rate limit excedido para IP (APCu): {$ip}");
            Response::error('Rate limit exceeded', 429);
        }
        return true;
    }

    private static function fileCheck(string $ip, int $limit, int $window): bool
    {
        return self::withLock(function () use ($ip, $limit, $window): bool {
            $now = time();
            $data = [];
            if (file_exists(self::$storageFile)) {
                $raw = (string) file_get_contents(self::$storageFile);
                $data = json_decode($raw, true) ?: [];
            }

            $key = sha1($ip);
            $entry = $data[$key] ?? ['requests' => [], 'blocked_until' => 0];
            $entry['requests'] = array_values(array_filter(
                $entry['requests'],
                fn($ts): bool => ($now - (int) $ts) <= $window
            ));

            if (($entry['blocked_until'] ?? 0) > $now) {
                Logger::warning("Rate limit bloqueado temporalmente para IP: {$ip}");
                Response::error('Rate limit exceeded', 429);
            }

            $entry['requests'][] = $now;
            if (count($entry['requests']) > $limit) {
                $entry['blocked_until'] = $now + $window;
                $data[$key] = $entry;
                self::persist($data);
                Logger::warning("Rate limit excedido para IP: {$ip}");
                Response::error('Rate limit exceeded', 429);
            }

            $data[$key] = $entry;
            if (random_int(1, self::$cleanupEvery) === 1) {
                self::cleanupOldEntries($data, $window, $now);
            }

            self::persist($data);
            return true;
        });
    }

    private static function persist(array $data): void
    {
        if (!is_dir(dirname(self::$storageFile))) {
            mkdir(dirname(self::$storageFile), 0775, true);
        }
        file_put_contents(self::$storageFile, json_encode($data), LOCK_EX);
    }

    private static function withLock(callable $callback): bool
    {
        if (!is_dir(dirname(self::$lockFile))) {
            mkdir(dirname(self::$lockFile), 0775, true);
        }

        $lock = fopen(self::$lockFile, 'c');
        if (!$lock) {
            throw new \RuntimeException('No se pudo crear archivo de lock para rate limiting');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('No se pudo adquirir lock para rate limiting');
            }
            return (bool) $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function cleanupOldEntries(array &$data, int $window, int $now): void
    {
        foreach ($data as $key => $entry) {
            $requests = array_values(array_filter(
                $entry['requests'] ?? [],
                fn($ts): bool => ($now - (int) $ts) <= $window
            ));
            $blockedUntil = (int) ($entry['blocked_until'] ?? 0);

            if ($requests === [] && $blockedUntil <= $now) {
                unset($data[$key]);
            } else {
                $data[$key]['requests'] = $requests;
            }
        }
    }
}
PHP;
    }

    public static function coreMiddleware(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

class Middleware
{
    /** @var array<string,string> */
    private static array $map = [];

    public static function register(string $name, string $callable): void
    {
        self::$map[$name] = $callable;
    }

    public static function handle(string $name): void
    {
        if (!isset(self::$map[$name])) {
            throw new \RuntimeException("Middleware no registrado: {$name}");
        }

        $callable = self::$map[$name];
        if (!str_contains($callable, '::')) {
            throw new \RuntimeException("Middleware invalido: {$name}");
        }

        [$class, $method] = explode('::', $callable, 2);
        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new \RuntimeException("Middleware no resoluble: {$name}");
        }

        $class::$method();
    }
}
PHP;
    }

    public static function httpResponseException(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core\Exceptions;

class HttpResponseException extends \RuntimeException
{
    public function __construct(private array $data, int $code = 400)
    {
        parent::__construct($data['message'] ?? 'HTTP response', $code);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
PHP;
    }

    public static function phpunitXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
  <testsuites>
    <testsuite name="Unit">
      <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Integration">
      <directory>tests/Integration</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML;
    }

    public static function healthTest(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HealthCheckTest extends TestCase
{
    private static $serverProcess = null;

    public static function setUpBeforeClass(): void
    {
        $cmd = PHP_BINARY . ' -S 127.0.0.1:18080 -t public';
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($cmd, $descriptor, $pipes, dirname(__DIR__, 2));
        if (!is_resource(self::$serverProcess)) {
            self::fail('No se pudo iniciar servidor embebido');
        }
        usleep(600000);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    public function testHealthContract(): void
    {
        $raw = @file_get_contents('http://127.0.0.1:18080/health');
        $this->assertNotFalse($raw, 'No se pudo consultar /health');

        $json = json_decode((string) $raw, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('status', $json['data']);
    }
}
PHP;
    }

    public static function coreJwt(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    public static function encode(array $payload): string
    {
        $secret = Env::get('JWT_SECRET', 'changeme');
        return FirebaseJWT::encode($payload, $secret, 'HS256');
    }

    public static function decode(string $token): array
    {
        $secret = Env::get('JWT_SECRET', 'changeme');
        return (array) FirebaseJWT::decode($token, new Key($secret, 'HS256'));
    }
}
PHP;
    }

    public static function authMiddleware(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\JWT;
use Core\Response;

class AuthMiddleware
{
    public static function handle(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            Response::error('Unauthorized', 401);
        }

        $token = trim(substr($header, 7));
        try {
            JWT::decode($token);
        } catch (\Throwable $e) {
            Response::error('Invalid token', 401);
        }
    }
}
PHP;
    }

    public static function authController(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use Core\Response;

class AuthController extends Controller
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function register(): void
    {
        $data = $this->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $result = $this->service->register((string) $data['email'], (string) $data['password']);
        Response::success($result, 'Usuario registrado', 201);
    }

    public function login(): void
    {
        $data = $this->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $result = $this->service->login((string) $data['email'], (string) $data['password']);
        Response::success($result, 'Login exitoso');
    }

    public function refresh(): void
    {
        $data = $this->validate([
            'token' => 'required|string|min_length:10',
        ]);

        $result = $this->service->refresh((string) $data['token']);
        Response::success($result, 'Token refrescado');
    }

    public function logout(): void
    {
        Response::success([], 'Logout exitoso');
    }

    public function me(): void
    {
        Response::success(['user' => 'current_user_placeholder'], 'Perfil');
    }
}
PHP;
    }

    public static function authService(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserModel;
use Core\Env;
use Core\JWT;

class AuthService
{
    private UserModel $users;

    public function __construct()
    {
        $this->users = new UserModel();
    }

    public function register(string $email, string $password): array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $id = $this->users->create(['email' => $email, 'password_hash' => $hash]);
        return ['id' => $id, 'email' => $email];
    }

    public function login(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            throw new \RuntimeException('Credenciales invalidas', 401);
        }

        $token = $this->issueAccessToken((string) $user['id'], (string) $user['email']);
        return ['access_token' => $token, 'token_type' => 'Bearer'];
    }

    public function refresh(string $token): array
    {
        $claims = JWT::decode($token);
        $sub = (string) ($claims['sub'] ?? '');
        $email = (string) ($claims['email'] ?? '');

        if ($sub === '' || $email === '') {
            throw new \RuntimeException('Token invalido para refresh', 401);
        }

        $newToken = $this->issueAccessToken($sub, $email);
        return ['access_token' => $newToken, 'token_type' => 'Bearer'];
    }

    private function issueAccessToken(string $sub, string $email): string
    {
        $now = time();
        $ttl = (int) Env::get('JWT_ACCESS_TOKEN_EXPIRATION', '900');
        if ($ttl < 60) {
            $ttl = 60;
        }

        return JWT::encode([
            'sub' => $sub,
            'email' => $email,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);
    }
}
PHP;
    }

    public static function userModel(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

class UserModel extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['email', 'password_hash'];

    public function create(array $data): string
    {
        $safe = $this->filterFillable($data);
        $sql = "INSERT INTO users (email, password_hash, created_at) VALUES (:email, :password_hash, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($safe);
        return (string) $this->db->lastInsertId();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }
}
PHP;
    }

    public static function refreshTokenModel(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

class RefreshTokenModel extends Model
{
    protected string $table = 'refresh_tokens';
    protected array $fillable = ['user_id', 'token_hash', 'expires_at'];
}
PHP;
    }

    public static function jwtDenylistModel(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

class JwtDenylistModel extends Model
{
    protected string $table = 'jwt_denylist';
    protected array $fillable = ['jti', 'expires_at'];
}
PHP;
    }

    public static function migrationUsers(string $dbType): string
    {
        if ($dbType === 'sqlsrv') {
            return <<<SQL
IF OBJECT_ID('users', 'U') IS NULL
CREATE TABLE users (
  id INT IDENTITY(1,1) PRIMARY KEY,
  email NVARCHAR(255) NOT NULL UNIQUE,
  password_hash NVARCHAR(255) NOT NULL,
  created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
SQL;
        }

        return <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    }

    public static function migrationRefreshTokens(string $dbType): string
    {
        if ($dbType === 'sqlsrv') {
            return <<<SQL
IF OBJECT_ID('refresh_tokens', 'U') IS NULL
CREATE TABLE refresh_tokens (
  id INT IDENTITY(1,1) PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash NVARCHAR(255) NOT NULL,
  expires_at DATETIME2 NOT NULL,
  created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
SQL;
        }

        return <<<SQL
CREATE TABLE IF NOT EXISTS refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    }

    public static function migrationDenylist(string $dbType): string
    {
        if ($dbType === 'sqlsrv') {
            return <<<SQL
IF OBJECT_ID('jwt_denylist', 'U') IS NULL
CREATE TABLE jwt_denylist (
  id INT IDENTITY(1,1) PRIMARY KEY,
  jti NVARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME2 NOT NULL,
  created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME()
);
SQL;
        }

        return <<<SQL
CREATE TABLE IF NOT EXISTS jwt_denylist (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jti VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;
    }

    public static function dockerfile(string $dbType): string
    {
        if ($dbType === 'sqlsrv') {
            return <<<'DOCKER'
FROM php:8.2-fpm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        gnupg2 \
        unixodbc \
        unixodbc-dev \
        apt-transport-https \
        ca-certificates \
    && curl -sSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor \
        > /usr/share/keyrings/microsoft-prod.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" \
        > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y --no-install-recommends msodbcsql18 \
    && (pecl install sqlsrv pdo_sqlsrv || pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0) \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
DOCKER;
        }

        return <<<'DOCKER'
FROM php:8.2-fpm
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
DOCKER;
    }

    public static function nginxConf(): string
    {
        return <<<NG
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass php:9000;
    }
}
NG;
    }

    public static function dockerHealthcheck(): string
    {
        return <<<'PHP'
<?php
http_response_code(200);
echo 'ok';
PHP;
    }

    public static function dockerCompose(string $dbType): string
    {
        $dbService = $dbType === 'sqlsrv'
            ? "  db:\n    image: mcr.microsoft.com/mssql/server:2022-latest\n    environment:\n      ACCEPT_EULA: Y\n      MSSQL_SA_PASSWORD: \${DB_PASS}\n    expose:\n      - \"1433\""
            : "  db:\n    image: mysql:8\n    environment:\n      MYSQL_DATABASE: \${DB_NAME:-app_db}\n      MYSQL_ROOT_PASSWORD: \${DB_PASS}\n    expose:\n      - \"3306\"";

        return "services:\n  php:\n    build:\n      context: .\n      dockerfile: docker/Dockerfile\n    volumes:\n      - ./:/var/www/html\n\n  nginx:\n    image: nginx:1.25-alpine\n    ports:\n      - \"8080:80\"\n    volumes:\n      - ./:/var/www/html\n      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro\n    depends_on:\n      - php\n\n{$dbService}\n";
    }
}



