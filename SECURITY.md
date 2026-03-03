# Security Policy

## Reporting a Vulnerability
- No publiques vulnerabilidades en issues publicos.
- Reporta hallazgos por canal privado con:
  - Componente afectado
  - Impacto estimado
  - Pasos de reproduccion
  - Evidencia minima (archivo/linea y comando)

## Supported Versions
- Se soporta la rama principal (`main`/`master`) y el ultimo tag estable.

## Security Baseline for Scaffold
- SQL Server:
  - `DB_ENCRYPT=1`
  - `DB_TRUST_SERVER_CERT=0` en entornos no locales.
- Nunca usar credenciales por defecto en produccion.
- Evitar DBs de sistema (`master`, `model`, `msdb`, `tempdb`) para datos de aplicacion.
- Ejecutar `composer test` antes de publicar release.

## Release Security Checklist
1. Ejecutar `composer validate --strict`.
2. Ejecutar `composer test`.
3. Verificar que templates no exponen secretos fijos.
4. Verificar hardening de transporte DB en comandos y scaffold.
