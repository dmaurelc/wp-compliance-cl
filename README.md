# WP Compliance CL

Plugin WordPress orientado a convertir los controles operativos de protección de datos en un **Compliance Hub** reutilizable para sitios chilenos.

> **Importante:** WP Compliance CL es una herramienta técnica y organizativa. No constituye asesoría legal, representación ni certificación de cumplimiento.

## Estado

**v0.1.2 — Updates & Distribution**

Esta release activa el proveedor desacoplado de actualizaciones, integra GitHub Releases con la interfaz nativa de WordPress y verifica cada paquete mediante SHA-256.

## Funciones actuales

- Dashboard con score orientativo, prioridades y estados `Completo`, `Pendiente` y `No evaluado`.
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
- Generación segura de borradores legales y detección de páginas de privacidad existentes.
- Exportación Markdown de inventario/RAT, DPA, transferencias, plan de brechas y EIPD.
- Escáner técnico local de plugins, usuarios, contenidos y servicios conocidos.
- Audit log con hash de integridad.
- Motor normativo separado en `src/Rules/Chile/Law21719/`.
- Migraciones automáticas y reparación idempotente del esquema `wp_ccl_*`.
- Actualizaciones nativas desde GitHub Releases con verificación SHA-256.

## Actualizaciones desde GitHub

El proveedor consulta el último release estable y utiliza exclusivamente el asset con nombre `wp-compliance-cl-{version}.zip` acompañado por `wp-compliance-cl-{version}.zip.sha256`.

Los repositorios públicos funcionan sin configuración adicional. Mientras el repositorio sea privado, crea un token fine-grained con permiso de solo lectura para **Contents** y defínelo fuera del plugin:

```php
define( 'WPCCL_GITHUB_TOKEN', 'github_pat_...' );
```

No guardes el token en el repositorio, el ZIP, las opciones de WordPress ni los registros. Esta instalación manual de `v0.1.2` habilita las actualizaciones automáticas para versiones posteriores.

## v0.1.1: migraciones

WP Compliance CL ya no depende exclusivamente del hook de activación para crear o actualizar tablas. En cada carga comprueba:

- `WPCCL_VERSION`
- `WPCCL_DB_VERSION`
- tablas obligatorias del esquema

Si falta una tabla o cambia el esquema, `Migrations::maybe_run()` ejecuta `dbDelta()` de forma idempotente y solo marca la migración como completada cuando todas las tablas requeridas existen.

Esto corrige instalaciones donde, por ejemplo, `wp_ccl_treatments` no se hubiera creado correctamente durante una activación anterior.

## Documentos

Las páginas nuevas generadas por el plugin se crean como **borrador**. Una página ya existente conserva su estado cuando se actualiza.

Antes de crear una nueva política, WP Compliance CL intenta detectar páginas de privacidad/protección de datos existentes para reducir duplicados. Los documentos incompletos muestran requisitos pendientes y no deben considerarse listos para publicación.

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
│   ├── AI/Security/             # interfaz reservada para BYOK v0.2
│   ├── Core/
│   ├── Frontend/
│   ├── Rules/Chile/Law21719/
│   └── Updates/                 # proveedor desacoplado de GitHub Releases
├── assets/
│   ├── css/
│   └── js/
├── docs/
├── readme.txt
└── uninstall.php
```

## Datos y privacidad

El plugin no necesita un SaaS externo ni transmite el inventario de compliance a terceros. Las detecciones del escáner se ejecutan en WordPress. Los datos se guardan en tablas locales `wp_ccl_*`.

Los registros se **preservan al desinstalar** por defecto para evitar destruir evidencia accidentalmente. Para purgar deliberadamente, define antes de desinstalar:

```php
define( 'WPCCL_PURGE_ON_UNINSTALL', true );
```

`CCL_PURGE_ON_UNINSTALL` se mantiene como alias compatible con instalaciones 0.1.0.

## Fuente normativa del pack Chile

El pack mantiene referencias a fuentes oficiales de BCN/Ley Chile y una fecha explícita de revisión. Las instrucciones futuras de la Agencia de Protección de Datos Personales deben incorporarse actualizando el pack sin acoplar cambios al resto del plugin.

## Roadmap

### 0.2.0 — Guided Compliance

- Wizard de configuración inicial.
- Autogeneración local basada en detecciones y presets.
- Scanner → propuestas revisables de tratamientos y proveedores.
- BYOK opcional con almacenamiento cifrado y Privacy Gateway.
- Proveedores OpenAI, Anthropic, Gemini y OpenAI-compatible.

### 0.3.0 — Integrations

Adaptadores avanzados para Bricks, WooCommerce, ACF y plugins de formularios/analítica habituales.

## Desarrollo

Requisitos:

- WordPress 6.5+
- PHP 8.1+

La base no requiere Node, Composer ni compilación para ejecutarse.

## Origen y atribución

WP Compliance CL fue desarrollado tomando como base conceptual y técnica el proyecto open source [compliance-cl](https://github.com/Lelemon-studio/compliance-cl), creado por Lelemon Studio / Lelemon SpA y distribuido bajo licencia MIT.

El proyecto original es una skill de Claude Code orientada a auditar repositorios y generar documentación de cumplimiento. WP Compliance CL es una adaptación independiente para WordPress que transforma ese enfoque en un plugin instalable, con panel administrativo, gestión local de datos, consentimientos y flujos operativos.

Los avisos y términos aplicables al proyecto original se conservan en [`THIRD-PARTY-NOTICES.md`](THIRD-PARTY-NOTICES.md).

## Licencia

El código de WP Compliance CL se distribuye bajo GPL-2.0-or-later. Los componentes o materiales derivados del proyecto original conservan además su atribución y licencia MIT correspondiente.
