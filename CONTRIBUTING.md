# Contributing

## Requisitos
- PHP 8.1+
- Composer

## Flujo recomendado
1. Crear rama de trabajo.
2. Implementar cambios con alcance acotado.
3. Ejecutar validaciones locales:
   - `composer validate --strict`
   - `composer test`
4. Actualizar documentacion afectada (`README.md`, `CHANGELOG.md`).
5. Abrir PR con evidencia de pruebas.

## Reglas
- No introducir credenciales fijas en templates.
- Mantener compatibilidad con `mysql` y `sqlsrv`.
- No editar `vendor/` manualmente.
- Todo cambio en comandos DB debe incluir test de seguridad/regresion.
