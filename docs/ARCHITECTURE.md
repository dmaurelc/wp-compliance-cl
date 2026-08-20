# Arquitectura

## Origen del enfoque

La arquitectura funcional y el enfoque de controles normativos toman como base conceptual y técnica el proyecto open source [compliance-cl](https://github.com/Lelemon-studio/compliance-cl), creado por Lelemon Studio / Lelemon SpA bajo licencia MIT. El proyecto original funciona como una skill de Claude Code; esta implementación adapta ese enfoque a la arquitectura, APIs, persistencia y experiencia administrativa de WordPress.

WP Compliance CL se divide en capas desacopladas:

- `Core/`: persistencia, migraciones, score, consentimiento, derechos, documentación y escáner.
- `Admin/`: Compliance Hub y workflows administrativos.
- `Frontend/`: banner, centro de privacidad y canal de derechos.
- `Rules/Chile/Law21719/`: pack normativo versionado y desacoplado.
- `Updates/`: contrato y proveedor de GitHub Releases, con soporte público/privado y verificación SHA-256.
- `AI/Security/`: frontera de seguridad para el futuro BYOK cifrado; todavía no contiene credenciales ni una implementación de proveedor.

## Versiones independientes

- `WPCCL_VERSION`: versión funcional del plugin.
- `WPCCL_DB_VERSION`: versión del esquema persistente.
- `WPCCL_LAW_PACK_VERSION`: fecha/versión del pack normativo.

Esto permite actualizar reglas jurídicas o estructura de datos sin confundirlas con la versión comercial/funcional.

## Migraciones

`Migrations::maybe_run()` se ejecuta antes de inicializar los módulos. Si cambia `WPCCL_DB_VERSION` o falta cualquiera de las tablas requeridas, vuelve a ejecutar `Database::install()` mediante `dbDelta()`.

La migración se considera completa únicamente si `Database::health()` confirma todas las tablas.

## Tablas

- `wp_ccl_treatments`
- `wp_ccl_providers`
- `wp_ccl_rights`
- `wp_ccl_right_events`
- `wp_ccl_consents`
- `wp_ccl_breaches`
- `wp_ccl_audit`

Los registros de consentimiento y auditoría se diseñan como eventos append-only. Los objetos operativos editables —tratamientos, proveedores y vulneraciones— mantienen fecha de creación/actualización.

## Actualizaciones

`GitHubReleaseProvider` implementa `UpdateProviderInterface` y se integra con el encabezado `Update URI` de WordPress. La consulta de metadata se almacena temporalmente durante seis horas y no expone credenciales.

Cada release debe incluir dos assets con nombres exactos:

- `wp-compliance-cl-{version}.zip`
- `wp-compliance-cl-{version}.zip.sha256`

El proveedor descarga el paquete mediante `upgrader_pre_download` y comprueba SHA-256 antes de permitir que `WP_Upgrader` lo instale. Para repositorios privados, `WPCCL_GITHUB_TOKEN` se define fuera del plugin y solo necesita acceso `Contents: read`.

## Extensión

Hooks actuales:

- `ccl_admin_capability`
- `ccl_scanner_findings`

La estrategia prevista para integraciones es añadir adaptadores independientes para Bricks, WooCommerce, Gravity Forms, WPForms, Fluent Forms, etc., sin introducir dependencias en el núcleo.
