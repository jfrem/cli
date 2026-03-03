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

## SQL Server + Docker (paso a paso)
```bash
php bin/php-init new demo-auth --preset=api-auth-jwt --database=sqlsrv --with-docker --no-interaction
cd demo-auth
docker compose up -d --build
docker compose exec -T php composer install
curl.exe http://localhost:8080/health
```

Si la base no existe, desde la raiz del proyecto generado:
```bash
php-init db:fresh --force
```

Smoke test JWT:
```bash
curl.exe -X POST http://localhost:8080/auth/register -H "Content-Type: application/json" -d "{\"email\":\"demo@example.com\",\"password\":\"Secret123!\"}"
curl.exe -X POST http://localhost:8080/auth/login -H "Content-Type: application/json" -d "{\"email\":\"demo@example.com\",\"password\":\"Secret123!\"}"
curl.exe -X POST http://localhost:8080/auth/refresh -H "Content-Type: application/json" -d "{\"token\":\"<ACCESS_TOKEN>\"}"
curl.exe -X GET http://localhost:8080/auth/me -H "Authorization: Bearer <ACCESS_TOKEN>"
```

## Seguridad aplicada
- `db:migrate` y `db:fresh` usan TLS SQL Server por variables de entorno (`DB_ENCRYPT`, `DB_TRUST_SERVER_CERT`).
- `db:fresh` bloquea DBs de sistema SQL Server: `master`, `model`, `msdb`, `tempdb`.
- En scaffold Docker SQL Server:
  - `DB_HOST=db`
  - `DB_NAME=app_db`
  - `DB_PASS` generado dinamicamente (sin credencial fija)
  - `DB_ENCRYPT=1`
  - `DB_TRUST_SERVER_CERT=1` (local Docker)
- `docker-compose` consume `${DB_PASS}` / `${DB_NAME}` en vez de secretos hardcodeados.

## Calidad y CI
Workflow en `.github/workflows/ci.yml`:
- `composer validate --strict`
- `composer install`
- `composer lint`
- `composer test`

## Release Draft
Incluye Release Drafter:
- Workflow: `.github/workflows/release-drafter.yml`
- Config: `.github/release-drafter.yml`

## Licencia
MIT. Ver `LICENSE`.
