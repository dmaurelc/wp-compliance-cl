# Arquitectura

WP Compliance CL se divide en cuatro capas:

## Origen del enfoque

La arquitectura funcional y el enfoque de controles normativos toman como base conceptual y técnica el proyecto open source [compliance-cl](https://github.com/Lelemon-studio/compliance-cl), creado por Lelemon Studio / Lelemon SpA bajo licencia MIT. El proyecto original funciona como una skill de Claude Code; esta implementación adapta ese enfoque a la arquitectura, APIs, persistencia y experiencia administrativa de WordPress.

- `Core/`: persistencia, consentimiento, derechos, documentación y escáner.
- `Admin/`: Compliance Hub y workflows administrativos.
- `Frontend/`: banner, centro de privacidad y canal de derechos.
- `Rules/Chile/Law21719/`: pack normativo versionado y desacoplado.

## Tablas

- `wp_ccl_treatments`
- `wp_ccl_providers`
- `wp_ccl_rights`
- `wp_ccl_right_events`
- `wp_ccl_consents`
- `wp_ccl_breaches`
- `wp_ccl_audit`

Los registros de consentimiento y auditoría se diseñan como eventos append-only. Los objetos operativos editables —tratamientos, proveedores y vulneraciones— mantienen fecha de creación/actualización.

## Extensión

Hooks disponibles desde la base:

- `ccl_admin_capability`
- `ccl_scanner_findings`

La estrategia prevista para integraciones es añadir adaptadores independientes para Bricks, WooCommerce, Gravity Forms, WPForms, Fluent Forms, etc., sin introducir dependencias en el núcleo.
