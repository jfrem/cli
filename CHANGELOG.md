# Changelog

## [Unreleased]

### Added
- Base del nuevo CLI en PHP con Symfony Console.
- Comandos `new`, `make:*`, `list:routes`, `db:migrate`, `db:fresh`, `init:docker`.
- Flujo interactivo extendido para `new` (preset, DB, entorno, CORS, JWT, tests y post-acciones).

### Changed
- Template de `core/Validator.php` mejorado para scaffolding: soporte de reglas `nullable`, `string`, `email`, `numeric`, `integer`, `boolean`, `alpha`, `date`, `in`, `min/max`, `min_length/max_length`, `min_value/max_value`.
- Template de `core/Database.php` ajustado para generar una sola DSN segun el motor elegido en `new` (`mysql` o `sqlsrv`), eliminando condicion dual en el scaffold.
- Template de `docker/Dockerfile` ajustado para respetar el motor seleccionado por usuario: `pdo_mysql` para MySQL y `sqlsrv/pdo_sqlsrv` para SQL Server.
- Mensajes y README de proyectos con `--with-docker` actualizados para flujo Docker-first (PHP y Nginx en contenedores) en lugar de sugerir `php -S`.
- Hardening del scaffold base:
  - `public/index.php` con headers adicionales de seguridad, timeout por `REQUEST_TIMEOUT_MS`, CORS/headers extendidos y rate limit parametrizado por entorno.
  - `core/Logger.php` con niveles por `LOG_LEVEL`, rotacion por tamano, retencion por dias, sanitizacion recursiva y normalizacion de excepciones.
  - `core/RateLimit.php` con lock file, fallback APCu -> archivo, bloqueo temporal por ventana, limpieza incremental y logging de eventos de limite.
  - `.env*` ahora incluye `RATE_LIMIT_MAX_REQUESTS` y `RATE_LIMIT_WINDOW_SECONDS`.
