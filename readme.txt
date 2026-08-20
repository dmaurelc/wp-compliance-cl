=== WP Compliance CL ===
Contributors: compliancecl
Tags: privacy, chile, ley 21719, consent, data protection, compliance
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.1
License: GPLv2 or later

Compliance Hub para WordPress orientado a la Ley 21.719 de Chile.

== Description ==

WP Compliance CL centraliza inventario de tratamientos, proveedores/encargados, derechos de titulares, consentimientos, vulneraciones, documentación y detección técnica local.

Incluye:
* Dashboard y score orientativo con estados Completo, Pendiente y No evaluado.
* Inventario de tratamientos y factores EIPD.
* Proveedores, DPA y transferencias internacionales.
* Canal público de derechos ARCO+ con expediente, plazo inicial y prórroga única.
* Banner de preferencias y centro de privacidad.
* Registro append-only de consentimientos con hash de prueba.
* Bloqueo por handle de scripts registrados en WordPress.
* Registro de vulneraciones.
* Generación de borradores de política, preferencias y ejercicio de derechos.
* Detección de páginas de privacidad existentes para evitar duplicados.
* Escáner local de plugins, contenido y servicios conocidos.
* Audit log de acciones administrativas.
* Migraciones automáticas y reparación del esquema de base de datos.

Este plugin no constituye asesoría legal ni certifica cumplimiento.

== Credits ==

WP Compliance CL fue desarrollado tomando como base conceptual y técnica el proyecto open source compliance-cl de Lelemon Studio / Lelemon SpA:
https://github.com/Lelemon-studio/compliance-cl

El proyecto original se distribuye bajo licencia MIT. WP Compliance CL es una adaptación independiente para WordPress y se distribuye bajo GPLv2 o posterior. Consulta THIRD-PARTY-NOTICES.md para conocer la atribución y los términos del proyecto original.

== Installation ==
1. Sube el ZIP desde Plugins > Añadir plugin > Subir plugin.
2. Activa WP Compliance CL.
3. Abre WP Compliance CL > Configuración y completa los datos del responsable.
4. Registra y revisa tratamientos y proveedores.
5. Genera borradores desde WP Compliance CL > Documentos y revisa antes de publicar.
6. Ejecuta el escáner y revisa cada hallazgo.

== Changelog ==
= 0.1.1 =
* Añade migraciones automáticas y reparación de tablas faltantes.
* Introduce estados Completo, Pendiente y No evaluado en el score.
* Centra el panel en pantallas grandes.
* Los documentos nuevos se generan como borrador.
* Detecta posibles páginas de privacidad existentes.
* Añade comprobaciones de preparación antes de publicar documentos.
* Prepara contratos internos para updates y BYOK de v0.2.

= 0.1.0 =
* Primera versión funcional.
