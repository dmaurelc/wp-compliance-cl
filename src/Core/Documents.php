<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Documents {
	public function existing_privacy_pages(): array {
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$matches = array();
		$settings = Util::settings();
		$managed  = array_filter( array_map( 'absint', array( $settings['policy_page_id'], $settings['cookie_page_id'], $settings['rights_page_id'] ) ) );

		foreach ( $pages as $page ) {
			if ( in_array( (int) $page->ID, $managed, true ) ) {
				continue;
			}
			$haystack = remove_accents( strtolower( $page->post_title . ' ' . $page->post_name ) );
			if ( false !== strpos( $haystack, 'privacidad' ) || false !== strpos( $haystack, 'proteccion de datos' ) || false !== strpos( $haystack, 'tratamiento de datos' ) || false !== strpos( $haystack, 'privacy' ) ) {
				$matches[] = $page;
			}
		}

		return $matches;
	}

	public function readiness( string $type ): array {
		$s = Util::settings();
		global $wpdb;
		$missing = array();

		if ( empty( $s['organisation_name'] ) ) {
			$missing[] = 'Identificar al responsable del tratamiento.';
		}
		if ( ! is_email( $s['privacy_email'] ) ) {
			$missing[] = 'Configurar un email de privacidad válido.';
		}

		if ( 'policy' === $type ) {
			$treatments = Database::exists( 'treatments' ) ? $wpdb->get_results( 'SELECT retention FROM ' . Database::table( 'treatments' ) . " WHERE status='active'" ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$providers  = Database::exists( 'providers' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'providers' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $treatments ) {
				$missing[] = 'Registrar y revisar al menos un tratamiento de datos.';
			} else {
				foreach ( $treatments as $treatment ) {
					if ( empty( $treatment->retention ) ) {
						$missing[] = 'Definir retención para todos los tratamientos activos.';
						break;
					}
			}
			}
			if ( 0 === $providers ) {
				$missing[] = 'Revisar y registrar los proveedores/encargados relevantes.';
			}
			if ( empty( $s['security_measures'] ) ) {
				$missing[] = 'Documentar las medidas de seguridad principales.';
			}
		}

		if ( 'cookies' === $type && empty( $s['consent_version'] ) ) {
			$missing[] = 'Definir la versión del aviso de consentimiento.';
		}

		return array(
			'ready'   => empty( $missing ),
			'missing' => array_values( array_unique( $missing ) ),
		);
	}

	private function draft_warning( string $type ): string {
		$readiness = $this->readiness( $type );
		if ( $readiness['ready'] ) {
			return '';
		}
		$out = '<div class="ccl-generated-document-warning"><p><strong>Borrador pendiente de revisión.</strong> Antes de publicar, completa y valida:</p><ul>';
		foreach ( $readiness['missing'] as $item ) {
			$out .= '<li>' . esc_html( $item ) . '</li>';
		}
		return $out . '</ul></div>';
	}
	public function policy_content(): string {
		$s = Util::settings();
		global $wpdb;
		$treatments = Database::exists( 'treatments' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'treatments' ) . " WHERE status='active' ORDER BY name ASC" ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$providers  = Database::exists( 'providers' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'providers' ) . ' ORDER BY name ASC' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$out  = $this->draft_warning( 'policy' );
		$out .= '<h2>Política de tratamiento de datos personales</h2>';
		$out .= '<p><strong>Responsable:</strong> ' . esc_html( $s['organisation_name'] ) . ( $s['rut'] ? ' — RUT ' . esc_html( $s['rut'] ) : '' ) . '.</p>';
		$out .= '<p><strong>Contacto de privacidad:</strong> <a href="mailto:' . esc_attr( antispambot( $s['privacy_email'] ) ) . '">' . esc_html( antispambot( $s['privacy_email'] ) ) . '</a>.</p>';
		if ( $s['address'] ) {
			$out .= '<p><strong>Domicilio:</strong> ' . esc_html( $s['address'] ) . '.</p>';
		}
		$out .= '<h3>Tratamientos y finalidades</h3>';
		if ( $treatments ) {
			$out .= '<ul>';
			foreach ( $treatments as $row ) {
				$out .= '<li><strong>' . esc_html( $row->name ) . ':</strong> ' . esc_html( $row->purpose ) . ' Base configurada: ' . esc_html( $row->lawful_basis ) . '. Datos: ' . esc_html( $row->data_categories ) . '. Titulares: ' . esc_html( $row->data_subjects ) . ( $row->retention ? '. Retención: ' . esc_html( $row->retention ) : '' ) . ( $row->recipients ? '. Destinatarios: ' . esc_html( $row->recipients ) : '' ) . '.</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p>El inventario de tratamientos aún está pendiente de completar en WP Compliance CL.</p>';
		}
		$out .= '<h3>Destinatarios, encargados y transferencias</h3>';
		if ( $providers ) {
			$out .= '<ul>';
			foreach ( $providers as $row ) {
				$out .= '<li><strong>' . esc_html( $row->name ) . '</strong> — ' . esc_html( $row->service ) . ( $row->country ? ' (' . esc_html( $row->country ) . ')' : '' ) . ( $row->international_transfer ? ' — transferencia internacional registrada' : '' ) . '.</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p>La revisión de proveedores y encargados aún está pendiente de completar.</p>';
		}
		$out .= '<h3>Derechos de las personas</h3><p>Puedes solicitar acceso, rectificación, supresión, oposición, portabilidad o bloqueo de tus datos mediante nuestro canal de privacidad. Cada solicitud será registrada y gestionada conforme a los plazos legales aplicables.</p>';
		$out .= '<h3>Seguridad y conservación</h3><p>Aplicamos medidas técnicas y organizativas proporcionales a los riesgos del tratamiento y procuramos conservar los datos únicamente durante el tiempo necesario para las finalidades declaradas o exigencias legales aplicables.</p>';
		$out .= '<h3>Consentimiento y retiro</h3><p>Cuando un tratamiento utilice consentimiento como base de licitud, este puede retirarse mediante los mecanismos habilitados en el sitio sin afectar la licitud del tratamiento previo a su retiro.</p>';
		$out .= '<h3>Versionado</h3><p>Versión de este aviso: ' . esc_html( $s['consent_version'] ) . '. Última generación: ' . esc_html( wp_date( 'd/m/Y' ) ) . '.</p>';
		$out .= '<p><em>WP Compliance CL entrega herramientas técnicas de apoyo al cumplimiento. Este documento es un borrador operativo y no constituye asesoría legal ni certificación de cumplimiento.</em></p>';
		return $out;
	}

	public function cookie_content(): string {
		return $this->draft_warning( 'cookies' ) . '<h2>Preferencias de privacidad y tecnologías de seguimiento</h2><p>Este sitio puede utilizar tecnologías necesarias para su funcionamiento y, cuando estén configuradas, tecnologías funcionales, analíticas o de marketing. Las categorías que utilicen consentimiento como base se mantienen desactivadas hasta que la persona manifieste su elección.</p><p>Puedes modificar o retirar tu elección posteriormente desde el centro de privacidad disponible en el sitio.</p><h3>Categorías</h3><ul><li><strong>Necesarias:</strong> esenciales para funciones básicas y seguridad.</li><li><strong>Funcionales:</strong> mejoran funciones no esenciales.</li><li><strong>Analítica:</strong> medición y comprensión del uso del sitio.</li><li><strong>Marketing:</strong> publicidad, atribución o seguimiento comercial.</li></ul>';
	}

	public function markdown( string $type ): string {
		$s = Util::settings();
		global $wpdb;
		$treatments = Database::exists( 'treatments' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'treatments' ) . ' ORDER BY name ASC' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$providers  = Database::exists( 'providers' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'providers' ) . ' ORDER BY name ASC' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$disclaimer = "\n\n---\n\n> WP Compliance CL entrega herramientas técnicas de apoyo al cumplimiento. Este documento no constituye asesoría legal ni certificación de cumplimiento.\n";

		if ( 'rat' === $type ) {
			$out  = "# Inventario / Registro de actividades de tratamiento\n\n";
			$out .= 'Responsable: ' . $s['organisation_name'] . "\n\nFecha de generación: " . wp_date( 'd/m/Y' ) . "\n\n";
			if ( ! $treatments ) {
				$out .= "No hay tratamientos registrados.\n";
			}
			foreach ( $treatments as $t ) {
				$out .= "## {$t->name}\n\n- Finalidad: {$t->purpose}\n- Titulares: {$t->data_subjects}\n- Datos: {$t->data_categories}\n- Base configurada: {$t->lawful_basis}\n- Retención: " . ( $t->retention ?: 'Pendiente' ) . "\n- Destinatarios: " . ( $t->recipients ?: 'No registrados' ) . "\n- Datos sensibles: " . ( $t->sensitive ? 'Sí' : 'No' ) . "\n- Estado EIPD: {$t->dpia_status}\n\n";
			}
			return $out . $disclaimer;
		}

		if ( 'dpa' === $type ) {
			$out  = "# Modelo base de acuerdo con encargado de tratamiento\n\n";
			$out .= "Este borrador debe completarse y acordarse con cada proveedor que actúe como encargado.\n\n";
			$out .= '## Partes' . "\n\n- Responsable: " . $s['organisation_name'] . ( $s['rut'] ? ' (' . $s['rut'] . ')' : '' ) . "\n- Encargado: [seleccionar proveedor]\n\n";
			$out .= "## Contenido mínimo operativo\n\n1. Objeto y duración del tratamiento.\n2. Naturaleza y finalidad.\n3. Categorías de datos y titulares.\n4. Tratamiento solo conforme a instrucciones documentadas.\n5. Confidencialidad y medidas de seguridad.\n6. Condiciones para subencargados.\n7. Asistencia para derechos de titulares e incidentes.\n8. Devolución o eliminación al término del servicio.\n9. Evidencia y cooperación razonable en auditorías.\n\n";
			if ( $providers ) {
				$out .= "## Proveedores registrados\n\n";
				foreach ( $providers as $p ) {
					$out .= "- {$p->name} — {$p->service} — DPA: {$p->dpa_status}\n";
				}
			}
			return $out . $disclaimer;
		}

		if ( 'transfers' === $type ) {
			$out   = "# Anexo de transferencias internacionales\n\n";
			$found = false;
			foreach ( $providers as $p ) {
				if ( ! $p->international_transfer ) {
					continue;
				}
				$found = true;
				$out .= "## {$p->name}\n\n- Servicio: {$p->service}\n- País/región: " . ( $p->country ?: 'Pendiente' ) . "\n- Datos: " . ( $p->data_categories ?: 'Pendiente' ) . "\n- Finalidad: " . ( $p->purpose ?: 'Pendiente' ) . "\n- Mecanismo/salvaguarda registrado: " . ( $p->transfer_mechanism ?: 'Pendiente de definir' ) . "\n- Subencargados: " . ( $p->subprocessors ?: 'No registrados' ) . "\n\n";
			}
			if ( ! $found ) {
				$out .= "No hay transferencias internacionales marcadas en el inventario actual.\n";
			}
			return $out . $disclaimer;
		}

		if ( 'breach-plan' === $type ) {
			$out  = "# Plan operativo de respuesta a vulneraciones\n\n";
			$out .= 'Responsable: ' . $s['organisation_name'] . "\nContacto de privacidad: " . $s['privacy_email'] . "\n\n";
			$out .= "## Flujo\n\n1. Contener y preservar evidencia.\n2. Registrar fecha de detección, naturaleza y sistemas afectados.\n3. Identificar categorías de datos y estimar titulares afectados.\n4. Evaluar riesgo para derechos y libertades.\n5. Adoptar medidas de mitigación.\n6. Evaluar y documentar si corresponde comunicar a la Agencia sin dilaciones indebidas.\n7. Evaluar comunicación a titulares cuando corresponda.\n8. Mantener evidencia, decisiones y medidas correctivas.\n9. Realizar revisión post-incidente.\n\nNo se incorpora un plazo automático de 72 horas. El procedimiento debe mantenerse actualizado con instrucciones oficiales chilenas vigentes.\n";
			return $out . $disclaimer;
		}

		if ( 'dpia' === $type ) {
			$out  = "# Evaluación preliminar de impacto en protección de datos\n\n";
			$high = array_filter( $treatments, static fn( $t ) => $t->sensitive || $t->large_scale || $t->automated_decisions || $t->public_monitoring );
			if ( ! $high ) {
				$out .= "No se detectaron flags de alto riesgo en los tratamientos registrados. Esto no sustituye una evaluación completa.\n";
			}
			foreach ( $high as $t ) {
				$out .= "## {$t->name}\n\n- Datos sensibles: " . ( $t->sensitive ? 'Sí' : 'No' ) . "\n- Gran escala: " . ( $t->large_scale ? 'Sí' : 'No' ) . "\n- Decisiones automatizadas significativas: " . ( $t->automated_decisions ? 'Sí' : 'No' ) . "\n- Monitorización sistemática de espacios públicos: " . ( $t->public_monitoring ? 'Sí' : 'No' ) . "\n- Estado interno: {$t->dpia_status}\n\n### Evaluación a completar\n\n- Necesidad y proporcionalidad del tratamiento.\n- Riesgos para derechos y libertades.\n- Medidas previstas para mitigar esos riesgos.\n- Salvaguardas, controles y evidencia.\n- Riesgo residual y decisión de continuar/modificar.\n\n";
			}
			return $out . $disclaimer;
		}

		return "# Documento no disponible\n" . $disclaimer;
	}
}
