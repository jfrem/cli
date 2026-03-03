# Changelog

## [Unreleased]

### Added
- Base del nuevo CLI en PHP con Symfony Console.
- Comandos `new`, `make:*`, `list:routes`, `db:migrate`, `db:fresh`, `init:docker`.
- Flujo interactivo extendido para `new` (preset, DB, entorno, CORS, JWT, tests y post-acciones).

### Changed
- Comandos DB endurecidos:
  - `db:migrate` ahora usa TLS SQL Server configurable por `.env` (`DB_ENCRYPT`, `DB_TRUST_SERVER_CERT`) y valida `DB_NAME`.
  - `db:fresh` ahora bloquea DBs de sistema SQL Server (`master`, `model`, `msdb`, `tempdb`) y valida `DB_NAME`.
- Scaffold SQL Server + Docker endurecido:
  - `DB_NAME` por defecto de aplicacion (`app_db`) en lugar de `master`.
  - `DB_PASS` generado dinamicamente (sin valor fijo).
  - `docker-compose` usa `${DB_PASS}` y `${DB_NAME}` desde `.env`.
- Calidad del CLI mejorada:
  - Se agrega suite de tests de integracion reales (scaffold seguro + guardas db).
  - CI ahora ejecuta `composer test`.
- Gobernanza tecnica reforzada:
  - Se agregan `SECURITY.md`, `CONTRIBUTING.md` y `.github/CODEOWNERS`.
- Template de `core/Validator.php` mejorado para scaffolding: soporte de reglas `nullable`, `string`, `email`, `numeric`, `integer`, `boolean`, `alpha`, `date`, `in`, `min/max`, `min_length/max_length`, `min_value/max_value`.
- Template de `core/Database.php` ajustado para generar una sola DSN segun el motor elegido en `new` (`mysql` o `sqlsrv`), eliminando condicion dual en el scaffold.
- Template de `docker/Dockerfile` ajustado para respetar el motor seleccionado por usuario: `pdo_mysql` para MySQL y `sqlsrv/pdo_sqlsrv` para SQL Server.
- Mensajes y README de proyectos con `--with-docker` actualizados para flujo Docker-first (PHP y Nginx en contenedores) en lugar de sugerir `php -S`.
- Hardening del scaffold base:
  - `public/index.php` con headers adicionales de seguridad, timeout por `REQUEST_TIMEOUT_MS`, CORS/headers extendidos y rate limit parametrizado por entorno.
  - `core/Logger.php` con niveles por `LOG_LEVEL`, rotacion por tamano, retencion por dias, sanitizacion recursiva y normalizacion de excepciones.
  - `core/RateLimit.php` con lock file, fallback APCu -> archivo, bloqueo temporal por ventana, limpieza incremental y logging de eventos de limite.
  - `.env*` ahora incluye `RATE_LIMIT_MAX_REQUESTS` y `RATE_LIMIT_WINDOW_SECONDS`.
- Correcciones de comando Docker:
  - `init:docker` ahora respeta `DB_TYPE` para generar `docker/Dockerfile` y `docker-compose.yml` consistentes con `mysql|sqlsrv`.
  - Se agrega test de integracion para evitar regresiones del stack SQL Server en `init:docker`.
- Seleccion de modalidad DB en `new`:
  - Nuevo `db-mode` interactivo/no interactivo: `docker` o `connection-string`.
  - Soporte de `DB_DSN` (`--db-dsn`) para integrar instancias existentes (locales/remotas).
  - `db:migrate` soporta `DB_DSN` con prioridad sobre `DB_HOST/DB_PORT/DB_NAME`.
  - `db:fresh` se bloquea cuando `DB_DSN` esta definido para evitar operaciones destructivas en bases existentes.
  - `init:docker` respeta `DB_MODE=connection-string` y omite el servicio DB en `docker-compose`.
- Compatibilidad local de runtime:
  - El `composer.json` generado por scaffold ahora declara `php ^8.1` para permitir pruebas locales `/health` en entornos con PHP 8.1.



