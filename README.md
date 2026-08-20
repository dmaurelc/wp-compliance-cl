# WP Compliance CL

Plugin WordPress orientado a convertir los controles operativos de protección de datos en un **Compliance Hub** reutilizable para sitios chilenos.

> **Importante:** WP Compliance CL es una herramienta técnica y organizativa. No constituye asesoría legal, representación ni certificación de cumplimiento.

## Estado

**v0.1.0 — functional foundation**

Esta versión implementa de punta a punta los módulos base necesarios para instalarla en un WordPress real y comenzar la configuración del cumplimiento.

## Funciones

- Dashboard con score orientativo y prioridades.
- Inventario de tratamientos, bases de licitud y retención.
- Flags de alto riesgo y estado EIPD.
- Proveedores/encargados, DPA y transferencias internacionales.
- Canal público ARCO+ mediante `[compliance_cl_rights_form]`.
- Expediente administrativo, fecha límite inicial de 30 días y prórroga única.
- Registro de eventos y respuesta por email.
- Banner de preferencias con aceptar / rechazar / configurar.
- Centro de privacidad mediante `[compliance_cl_privacy_center]`.
- Consentimientos versionados, append-only y con hash HMAC de evidencia.
- Integración oportunista con WP Consent API si `wp_set_consent()` está disponible.
- Bloqueo previo de scripts registrados por `wp_enqueue_script()` mediante reglas `handle=categoria`.
- Registro de vulneraciones de seguridad.
- Generación/actualización de páginas públicas.
- Escáner técnico local de plugins, usuarios, contenidos y servicios conocidos.
- Audit log con hash de integridad.
- Motor normativo separado en `src/Rules/Chile/Law21719/`.

## Ejemplo de reglas para scripts

En **WP Compliance CL → Configuración**:

```text
google-analytics=analytics
meta-pixel=marketing
chat-widget=functional
```

Categorías permitidas: `necessary`, `functional`, `analytics`, `marketing`.

## Arquitectura

```text
wp-compliance-cl/
├── wp-compliance-cl.php
├── src/
│   ├── Admin/
│   ├── Core/
│   ├── Frontend/
│   └── Rules/Chile/Law21719/
├── assets/
│   ├── css/
│   └── js/
├── readme.txt
└── uninstall.php
```

## Datos y privacidad

El plugin no necesita un SaaS externo ni transmite el inventario de compliance a terceros. Las detecciones del escáner se ejecutan en WordPress. Los datos se guardan en tablas locales `wp_ccl_*`.

Los registros se **preservan al desinstalar** por defecto para evitar destruir evidencia accidentalmente. Para purgar deliberadamente, define antes de desinstalar:

```php
define( 'WPCCL_PURGE_ON_UNINSTALL', true );
```

## Fuente normativa del pack Chile

El pack mantiene referencias a fuentes oficiales de BCN/Ley Chile y una fecha explícita de revisión. Las instrucciones futuras de la Agencia de Protección de Datos Personales deben incorporarse actualizando el pack sin acoplar cambios al resto del plugin.

## Desarrollo

Requisitos:

- WordPress 6.5+
- PHP 8.1+

La base no requiere Node, Composer ni compilación para ejecutarse. Esto facilita instalar directamente el ZIP en proyectos de clientes.

## Origen y atribución

WP Compliance CL fue desarrollado tomando como base conceptual y técnica el proyecto open source [compliance-cl](https://github.com/Lelemon-studio/compliance-cl), creado por Lelemon Studio / Lelemon SpA y distribuido bajo licencia MIT.

El proyecto original es una skill de Claude Code orientada a auditar repositorios y generar documentación de cumplimiento. WP Compliance CL es una adaptación independiente para WordPress que transforma ese enfoque en un plugin instalable, con panel administrativo, gestión local de datos, consentimientos y flujos operativos.

Los avisos y términos aplicables al proyecto original se conservan en [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md).

## Licencia

El código de WP Compliance CL se distribuye bajo GPL-2.0-or-later. Los componentes o materiales derivados del proyecto original conservan además su atribución y licencia MIT correspondiente.
