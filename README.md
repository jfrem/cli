# php-init (CLI PHP)

CLI interactivo para generar scaffolding de APIs PHP MVC con buenas practicas, presets y soporte MySQL/SQL Server.

## Requisitos
- PHP `^8.1` para ejecutar el CLI.
- Composer.

Nota: el proyecto generado por defecto declara `php ^8.2` en su `composer.json`.

## Instalacion local (desarrollo)
```bash
composer install
php bin/php-init list
```

## Instalacion global (usuario)
```bash
composer global require audfact/php-init
php-init list
```

## Comandos
- `php-init new <nombre>`
- `php-init make:controller <nombre>`
- `php-init make:model <nombre> [tabla]`
- `php-init make:middleware <nombre>`
- `php-init make:crud <nombre>`
- `php-init list:routes`
- `php-init db:migrate`
- `php-init db:fresh --force`
- `php-init init:docker`

## Flujo interactivo (`new`)
`new` pregunta por preset, motor de BD y parametros clave. Tambien puedes usar flags:

- `--preset=api-basic|api-auth-jwt|api-enterprise`
- `--database=mysql|sqlsrv`
- `--db-host`, `--db-port`, `--db-name`, `--db-user`, `--db-pass`
- `--env=development|production`
- `--allowed-origins=*|https://app.com,https://admin.com`
- `--jwt-access-exp=<segundos>`
- `--jwt-refresh-exp=<segundos>`
- `--with-docker`
- `--no-tests`
- `--run-composer`
- `--run-migrate`
- `--no-interaction`

Ejemplo no interactivo:
```bash
php bin/php-init new demo-auth \
  --preset=api-auth-jwt \
  --database=sqlsrv \
  --db-host=localhost \
  --db-port=1433 \
  --db-name=demo_auth \
  --db-user=sa \
  --db-pass=Secret123 \
  --env=production \
  --allowed-origins=https://app.example.com \
  --with-docker \
  --no-interaction
```

## CI
Incluye workflow de GitHub Actions en `.github/workflows/ci.yml`:
- `composer validate --strict`
- `composer install`
- `composer lint`

## Release Draft
Incluye Release Drafter:
- Workflow: `.github/workflows/release-drafter.yml`
- Config: `.github/release-drafter.yml`

## Licencia
MIT. Ver `LICENSE`.


## SQL Server + Docker (paso a paso)
Para el preset `api-auth-jwt` con SQL Server, este es el flujo recomendado de verificacion:

```bash
php bin/php-init new demo-auth --preset=api-auth-jwt --database=sqlsrv --with-docker --no-interaction
cd demo-auth
docker compose up -d --build
docker compose exec -T php composer install
curl.exe http://localhost:8080/health
```

Si el `health` reporta `database: down`, aplica migraciones SQL iniciales (preset JWT):

```bash
docker compose exec -T php php -r "
$pdo = new PDO('sqlsrv:Server=db,1433;Database=master;Encrypt=1;TrustServerCertificate=1', 'sa', 'YourStrong!Passw0rd');
foreach (['users.sql','refresh_tokens.sql','jwt_denylist.sql'] as $f) {
  $sql = file_get_contents('/var/www/html/database/migrations/sqlsrv/' . $f);
  $pdo->exec($sql);
}
echo 'migrations_ok' . PHP_EOL;
"
```

Smoke test JWT:

```bash
curl.exe -X POST http://localhost:8080/auth/register -H "Content-Type: application/json" -d "{\"email\":\"demo@example.com\",\"password\":\"Secret123!\"}"
curl.exe -X POST http://localhost:8080/auth/login -H "Content-Type: application/json" -d "{\"email\":\"demo@example.com\",\"password\":\"Secret123!\"}"
curl.exe -X POST http://localhost:8080/auth/refresh -H "Content-Type: application/json" -d "{\"token\":\"<ACCESS_TOKEN>\"}"
curl.exe -X GET http://localhost:8080/auth/me -H "Authorization: Bearer <ACCESS_TOKEN>"
```

Notas:
- Con `--with-docker` + `sqlsrv`, el scaffold ya genera `DB_HOST=db`, `DB_NAME=master`, `DB_PASS=YourStrong!Passw0rd`, `DB_ENCRYPT=1`, `DB_TRUST_SERVER_CERT=1`.
- El contenedor PHP generado incluye `composer`, `git` y `unzip` para permitir `composer install` dentro del contenedor.
