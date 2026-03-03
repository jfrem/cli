# Release Checklist - php-init CLI

Fecha: 2026-03-03

## 1. Calidad de codigo
- [x] Ejecutar `composer validate --strict`
- [x] Ejecutar `composer lint`
- [x] Verificar que no hay errores de sintaxis en `src/`

## 2. Generacion de scaffold
- [x] Probar `php bin/php-init new demo-auth --preset=api-auth-jwt --database=sqlsrv --with-docker --no-interaction`
- [x] Confirmar que `.env` generado (Docker + SQL Server) incluye:
  - `DB_HOST=db`
  - `DB_NAME=master`
  - `DB_PASS=YourStrong!Passw0rd`
  - `DB_ENCRYPT=1`
  - `DB_TRUST_SERVER_CERT=1`
- [x] Confirmar que `.gitignore` contiene `/.env.*` y excepcion `!/.env.example`

## 3. Docker runtime
- [x] Ejecutar `docker compose up -d --build`
- [x] Confirmar build SQL Server sin `apt-key` (keyring + signed-by)
- [x] Confirmar instalacion de extensiones `sqlsrv/pdo_sqlsrv` con fallback 5.12.0 para PHP 8.2
- [x] Confirmar que imagen PHP incluye `composer`, `git` y `unzip`
- [x] Ejecutar `docker compose exec -T php composer install`
- [x] Probar `curl.exe http://localhost:8080/health` (validado en puerto alterno 8092)

## 4. Seguridad y hardening del scaffold
- [x] Verificar placeholders de secretos en `.env.example`, `.env.test`, `.env.prod`
- [x] Verificar `ALLOWED_ORIGINS` vacio en produccion
- [x] Revisar headers de seguridad en `public/index.php`
- [x] Revisar rate limit parametrizable (`RATE_LIMIT_MAX_REQUESTS`, `RATE_LIMIT_WINDOW_SECONDS`)

## 5. Auth JWT
- [x] Verificar rutas `/auth/login`, `/auth/refresh`, `/auth/me`
- [x] Verificar `refresh()` implementado y funcionando
- [x] Validar claims `iat`, `nbf`, `exp` en access token

## 6. Release git
- [ ] `git status` limpio
- [ ] Commit con mensaje de release
- [ ] Tag semantico (ej: `v1.1.0`)
- [ ] Publicar release notes con cambios de Docker + SQL Server + hardening

## 7. Post-release
- [x] Generar demo final de smoke test
- [ ] Documentar comandos en README
- [ ] Cerrar hallazgos pendientes en backlog tecnico

## Notas de validacion
- Para validar `register/login/refresh` en SQL Server fue necesario ejecutar migraciones SQL del scaffold (`users`, `refresh_tokens`, `jwt_denylist`) en la base configurada (`master`).
