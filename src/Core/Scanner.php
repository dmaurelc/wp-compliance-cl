<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Scanner {
	public function run(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$active = (array) get_option( 'active_plugins', array() );
		$theme  = wp_get_theme();
		$findings = array();

		$detectors = array(
			'woocommerce/'       => array( 'WooCommerce', 'commerce', 'Usuarios, pedidos, direcciones y potencialmente datos de pago delegados.' ),
			'advanced-custom-fields' => array( 'Advanced Custom Fields', 'data-model', 'Campos personalizados que pueden contener datos personales.' ),
			'bricks/'            => array( 'Bricks Builder', 'forms', 'Puede contener formularios y envíos guardados.' ),
			'contact-form-7/'    => array( 'Contact Form 7', 'forms', 'Formularios de contacto y posibles integraciones externas.' ),
			'gravityforms/'      => array( 'Gravity Forms', 'forms', 'Entradas de formulario almacenables.' ),
			'fluentform/'        => array( 'Fluent Forms', 'forms', 'Entradas de formulario y automatizaciones.' ),
			'wpforms/'           => array( 'WPForms', 'forms', 'Formularios y entradas.' ),
			'google-site-kit/'   => array( 'Google Site Kit', 'analytics', 'Puede integrar Analytics, Search Console u otros servicios Google.' ),
			'google-analytics'   => array( 'Google Analytics integration', 'analytics', 'Analítica y potenciales identificadores.' ),
			'wordfence/'         => array( 'Wordfence', 'security', 'Registros de seguridad, IP y eventos.' ),
			'fluent-smtp/'       => array( 'FluentSMTP', 'email', 'Enrutamiento de correos a un proveedor externo y logs configurables.' ),
			'wp-mail-smtp/'      => array( 'WP Mail SMTP', 'email', 'Enrutamiento de correos a un proveedor externo y logs configurables.' ),
		);

		foreach ( $active as $plugin ) {
			foreach ( $detectors as $needle => $meta ) {
				if ( false !== stripos( $plugin, $needle ) ) {
					$findings[] = array( 'name' => $meta[0], 'type' => $meta[1], 'detail' => $meta[2], 'evidence' => $plugin, 'status' => 'review' );
				}
			}
		}

		$theme_name = $theme->get( 'Name' );
		if ( $theme_name ) {
			$findings[] = array( 'name' => 'Tema activo', 'type' => 'theme', 'detail' => $theme_name, 'evidence' => $theme->get_stylesheet(), 'status' => 'info' );
		}

		$parent = $theme->parent();
		if ( false !== stripos( $theme_name, 'bricks' ) || ( $parent && false !== stripos( $parent->get( 'Name' ), 'bricks' ) ) ) {
			$findings[] = array( 'name' => 'Bricks Builder', 'type' => 'forms', 'detail' => 'Bricks está activo como tema/builder. Revisa formularios y submissions guardados.', 'evidence' => $theme_name, 'status' => 'review' );
		}

		global $wpdb;
		$patterns = array(
			'google-analytics.com' => 'Google Analytics', 'googletagmanager.com' => 'Google Tag Manager',
			'connect.facebook.net' => 'Meta Pixel', 'clarity.ms' => 'Microsoft Clarity', 'hotjar.com' => 'Hotjar',
			'recaptcha' => 'reCAPTCHA', 'youtube.com' => 'YouTube', 'vimeo.com' => 'Vimeo', 'maps.google' => 'Google Maps',
		);
		foreach ( $patterns as $needle => $label ) {
			$like = '%' . $wpdb->esc_like( $needle ) . '%';
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft') AND post_content LIKE %s", $like ) );
			if ( $count > 0 ) {
				$findings[] = array( 'name' => $label, 'type' => 'external-service', 'detail' => "Detectado en contenido de {$count} entrada(s)/página(s).", 'evidence' => $needle, 'status' => 'review' );
			}
		}

		$bricks_meta_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bricks_%' AND (meta_value LIKE '%email%' OR meta_value LIKE '%phone%' OR meta_value LIKE '%telefono%' OR meta_value LIKE '%form%')" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $bricks_meta_count > 0 ) {
			$findings[] = array( 'name' => 'Campos/formularios en metadatos Bricks', 'type' => 'personal-data', 'detail' => "Se detectaron {$bricks_meta_count} registros Bricks con indicadores de formularios o datos personales.", 'evidence' => '_bricks_* postmeta', 'status' => 'review' );
		}

		$users = count_users();
		if ( ! empty( $users['total_users'] ) ) {
			$findings[] = array( 'name' => 'Usuarios WordPress', 'type' => 'personal-data', 'detail' => (int) $users['total_users'] . ' usuario(s) registrados. WordPress almacena identificadores y datos de cuenta.', 'evidence' => 'wp_users', 'status' => 'review' );
		}

		return apply_filters( 'ccl_scanner_findings', $findings );
	}
}
