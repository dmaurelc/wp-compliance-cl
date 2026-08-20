# Changelog

## 0.1.2 — 2026-08-20

### Updates & Distribution

- Implementa `GitHubReleaseProvider` sobre el contrato desacoplado de actualizaciones.
- Añade el encabezado estándar `Update URI` y la integración nativa con el panel de plugins de WordPress.
- Consulta únicamente el último release estable de GitHub.
- Exige assets versionados con ZIP y checksum SHA-256.
- Verifica la integridad del paquete antes de entregarlo a `WP_Upgrader`.
- Admite repositorios públicos sin credenciales y repositorios privados mediante `WPCCL_GITHUB_TOKEN`.
- Mantiene las credenciales fuera del plugin y permite sobrescribir repositorio/token mediante filtros.
- Añade pruebas estáticas del contrato de actualizaciones y del metadata de release.

## 0.1.1 — 2026-08-20

### Stability & Foundations

- Añade `WPCCL_DB_VERSION` y un gestor de migraciones idempotente.
- Repara automáticamente tablas faltantes, incluido `ccl_treatments`.
- El plugin valida el esquema también durante upgrades, no solo durante activación.
- Separa versión del plugin, esquema de base de datos y law pack.
- El score distingue `Completo`, `Pendiente` y `No evaluado`; los estados desconocidos ya no generan falsos positivos.
- Añade resumen de estados al score y etiquetas de severidad en español.
- Centra el Compliance Hub en pantallas grandes manteniendo un ancho máximo legible.
- Añade alerta visible si el esquema de datos permanece incompleto.
- Detecta páginas de privacidad/protección de datos existentes para reducir duplicados.
- Las páginas nuevas generadas por WP Compliance CL se crean como borrador.
- Los documentos incompletos muestran requisitos pendientes antes de publicación.
- Elimina del frontend el mensaje técnico de revisión de tecnologías que pertenecía al administrador.
- Prepara `UpdateProviderInterface` para updates desacoplados.
- Prepara `SecretStoreInterface` como frontera de seguridad del futuro módulo BYOK.
- Conserva la atribución y licencia MIT del proyecto original `Lelemon-studio/compliance-cl`.


## 0.1.0 — 2026-08-20

- Compliance Hub administrativo.
- Pack normativo Chile/Ley 21.719 desacoplado.
- Inventario de tratamientos y factores EIPD.
- Proveedores, DPA y transferencias internacionales.
- Canal ARCO+ con expediente, plazo y prórroga.
- Consent banner, centro de privacidad y registro de evidencia.
- Bloqueo de scripts WordPress por handle/categoría.
- Registro de vulneraciones.
- Generación de páginas públicas y documentos internos Markdown.
- Escáner técnico local con detección inicial de Bricks, ACF, formularios y servicios externos.
- Audit log e integridad HMAC.
- GitHub Actions para lint PHP 8.1–8.3.
