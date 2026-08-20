<?php
namespace WPComplianceCL\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Score {
	public function build(): array {
		global $wpdb;
		$s = Util::settings();

		$treatments = Database::exists( 'treatments' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'treatments' ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$providers  = Database::exists( 'providers' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'providers' ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rights_total = Database::exists( 'rights' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'rights' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$breaches_total = Database::exists( 'breaches' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'breaches' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$policy_ok = ! empty( $s['policy_page_id'] ) && 'publish' === get_post_status( (int) $s['policy_page_id'] );
		$rights_ok = ! empty( $s['rights_page_id'] ) && 'publish' === get_post_status( (int) $s['rights_page_id'] );

		$has_treatments = ! empty( $treatments );
		$has_providers  = ! empty( $providers );

		$all_retention = $has_treatments;
		foreach ( $treatments as $treatment ) {
			if ( empty( $treatment->retention ) ) {
				$all_retention = false;
				break;
			}
		}

		$all_dpa = true;
		foreach ( $providers as $provider ) {
			if ( 'processor' === $provider->role && 'signed' !== $provider->dpa_status ) {
				$all_dpa = false;
				break;
			}
		}

		$transfers_ok = true;
		foreach ( $providers as $provider ) {
			if ( $provider->international_transfer && empty( $provider->transfer_mechanism ) ) {
				$transfers_ok = false;
				break;
			}
		}

		$high_risk = array_filter(
			$treatments,
			static fn( $treatment ) => $treatment->sensitive || $treatment->large_scale || $treatment->automated_decisions || $treatment->public_monitoring
		);
		$dpia_ok = true;
		foreach ( $high_risk as $treatment ) {
			if ( 'completed' !== $treatment->dpia_status ) {
				$dpia_ok = false;
				break;
			}
		}

		$consent_treatments = array_filter(
			$treatments,
			static fn( $treatment ) => 'consent' === $treatment->lawful_basis
		);

		$controls = array(
			$this->control( 'ORG-01', 'Responsable y canal de privacidad identificados', 'Arts. 14 ter y 14 quáter', 'high', 'ccl-settings', ! empty( $s['organisation_name'] ) && is_email( $s['privacy_email'] ) ? 'ok' : 'pending' ),
			$this->control( 'TRT-01', 'Inventario y base de licitud', 'Principios y arts. 12-13', 'high', 'ccl-treatments', $has_treatments ? 'ok' : 'unknown' ),
			$this->control( 'MIN-01', 'Retención definida', 'Minimización / privacidad por defecto', 'medium', 'ccl-treatments', ! $has_treatments ? 'unknown' : ( $all_retention ? 'ok' : 'pending' ) ),
			$this->control( 'DER-01', 'Canal de derechos publicado', 'Arts. 4 y 11', 'high', 'ccl-documents', $rights_ok ? 'ok' : 'pending' ),
			$this->control( 'DER-02', 'Workflow de solicitudes operativo', 'Art. 11', 'high', 'ccl-rights', ( $rights_ok || $rights_total > 0 ) ? 'ok' : 'unknown' ),
			$this->control( 'CON-01', 'Consentimiento versionado y revocable', 'Art. 12', 'high', 'ccl-consents', empty( $consent_treatments ) && empty( $s['consent_enabled'] ) ? 'unknown' : ( ! empty( $s['consent_enabled'] ) && ! empty( $s['consent_version'] ) ? 'ok' : 'pending' ) ),
			$this->control( 'ENC-01', 'Encargados y DPA documentados', 'Art. 15 bis', 'medium', 'ccl-providers', ! $has_providers ? 'unknown' : ( $all_dpa ? 'ok' : 'pending' ) ),
			$this->control( 'TRF-01', 'Transferencias internacionales documentadas', 'Régimen de transferencias', 'high', 'ccl-providers', ! $has_providers ? 'unknown' : ( $transfers_ok ? 'ok' : 'pending' ) ),
			$this->control( 'SEG-01', 'Medidas de seguridad registradas', 'Art. 14 quinquies', 'high', 'ccl-settings', ! empty( $s['security_measures'] ) ? 'ok' : 'pending' ),
			$this->control( 'BRE-01', 'Procedimiento de brechas preparado', 'Art. 14 sexies', 'high', 'ccl-breaches', ( ! empty( $s['breach_procedure_ready'] ) || $breaches_total > 0 ) ? 'ok' : 'pending' ),
			$this->control( 'EIPD-01', 'EIPD para tratamientos de alto riesgo', 'Art. 15 ter', 'high', 'ccl-treatments', ! $has_treatments ? 'unknown' : ( empty( $high_risk ) || $dpia_ok ? 'ok' : 'pending' ) ),
			$this->control( 'TRA-01', 'Política de tratamiento publicada', 'Art. 14 ter', 'medium', 'ccl-documents', $policy_ok ? 'ok' : 'pending' ),
		);

		$complete = count( array_filter( $controls, static fn( $control ) => 'ok' === $control['state'] ) );
		$unknown  = count( array_filter( $controls, static fn( $control ) => 'unknown' === $control['state'] ) );
		$pending  = count( $controls ) - $complete - $unknown;
		$percent  = (int) round( ( $complete / count( $controls ) ) * 100 );

		$next = $this->next_control( $controls );
		$label = $percent >= 85 ? 'Postura sólida' : ( $percent >= 60 ? 'En progreso' : 'Requiere configuración' );

		return compact( 'percent', 'controls', 'next', 'label', 'complete', 'pending', 'unknown' );
	}

	private function control( string $id, string $title, string $article, string $severity, string $page, string $state ): array {
		$labels = array(
			'ok'      => 'Completo',
			'pending' => 'Pendiente',
			'unknown' => 'No evaluado',
		);

		return array(
			'id'          => $id,
			'title'       => $title,
			'article'     => $article,
			'severity'    => $severity,
			'page'        => $page,
			'state'       => $state,
			'state_label' => $labels[ $state ] ?? 'No evaluado',
		);
	}

	private function next_control( array $controls ): array {
		foreach ( array( 'pending', 'unknown' ) as $state ) {
			foreach ( $controls as $control ) {
				if ( $state === $control['state'] && 'high' === $control['severity'] ) {
					return $control;
				}
			}
		}

		foreach ( $controls as $control ) {
			if ( 'ok' !== $control['state'] ) {
				return $control;
			}
		}

		return array(
			'title'    => 'Mantener revisión periódica',
			'article'  => 'Todos los controles configurados.',
			'severity' => 'low',
			'page'     => 'ccl-scanner',
			'state'    => 'ok',
		);
	}
}
