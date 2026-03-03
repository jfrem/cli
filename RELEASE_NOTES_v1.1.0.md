# Release Notes v1.1.0

## Cambios principales
- Hardening del scaffold generado (headers de seguridad, rate limit configurable, logger mejorado y sanitizacion).
- Soporte Docker SQL Server corregido:
  - Eliminacion de `apt-key` (keyring + `signed-by`).
  - Instalacion de `sqlsrv/pdo_sqlsrv` con fallback compatible PHP 8.2 (`5.12.0`).
  - Imagen PHP incluye `composer`, `git` y `unzip`.
- Configuracion `.env` para `--with-docker --database=sqlsrv` lista para ejecutar:
  - `DB_HOST=db`
  - `DB_NAME=app_db`
  - `DB_PASS` generado dinamicamente (sin secreto fijo)
  - `DB_ENCRYPT=1`
  - `DB_TRUST_SERVER_CERT=1`
- Plantillas JWT mejoradas:
  - `refresh()` implementado en controlador/servicio.
  - Claims `iat`, `nbf`, `exp` en access token.
- Testing de scaffold mejorado:
  - `HealthCheckTest` funcional en lugar de placeholder.
- Documentacion operativa ampliada en README para SQL Server + Docker + smoke test JWT.

## Verificacion de release
- `composer validate --strict`: OK
- `composer lint`: OK
- Smoke test Docker + SQL Server + JWT: OK
- Inicializacion DB local: `php-init db:fresh --force` (crea `app_db` y aplica migraciones)

## Commit y tag
- Commit: `82d371d`
- Tag: `v1.1.0`
