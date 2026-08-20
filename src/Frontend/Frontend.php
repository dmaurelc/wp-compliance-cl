<?php
namespace WPComplianceCL\Frontend;

use WPComplianceCL\Core\Util;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Frontend {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_footer', array( $this, 'banner' ), 50 );
		add_shortcode( 'compliance_cl_rights_form', array( $this, 'rights_form' ) );
		add_shortcode( 'compliance_cl_privacy_center', array( $this, 'privacy_center' ) );
	}

	public function assets(): void {
		$s = Util::settings();
		wp_enqueue_style( 'ccl-frontend', WPCCL_URL . 'assets/css/frontend.css', array(), WPCCL_VERSION );
		wp_enqueue_script( 'ccl-frontend', WPCCL_URL . 'assets/js/frontend.js', array(), WPCCL_VERSION, true );
		wp_localize_script(
			'ccl-frontend',
			'WPComplianceCL',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'ccl_consent' ),
				'consentVersion' => (string) $s['consent_version'],
				'accent'         => (string) $s['accent_color'],
				'bannerEnabled'  => ! empty( $s['consent_enabled'] ),
			)
		);
	}

	public function banner(): void {
		$s = Util::settings();
		if ( empty( $s['consent_enabled'] ) ) { return; }
		?>
		<div class="ccl-consent" id="ccl-consent" hidden style="--ccl-accent:<?php echo esc_attr( $s['accent_color'] ); ?>">
			<div class="ccl-consent__panel" role="dialog" aria-modal="true" aria-labelledby="ccl-consent-title">
				<div class="ccl-consent__copy">
					<span class="ccl-consent__eyebrow"><?php esc_html_e( 'Privacidad', 'wp-compliance-cl' ); ?></span>
					<h2 id="ccl-consent-title"><?php esc_html_e( 'Tus datos, bajo tu control', 'wp-compliance-cl' ); ?></h2>
					<p><?php esc_html_e( 'Usamos tecnologías necesarias para que el sitio funcione y, con tu elección, tecnologías funcionales, analíticas o de marketing. Puedes cambiar esta decisión cuando quieras.', 'wp-compliance-cl' ); ?></p>
				</div>
				<div class="ccl-consent__actions">
					<button type="button" class="ccl-consent__button" data-ccl-action="reject"><?php esc_html_e( 'Rechazar', 'wp-compliance-cl' ); ?></button>
					<button type="button" class="ccl-consent__button" data-ccl-action="configure"><?php esc_html_e( 'Configurar', 'wp-compliance-cl' ); ?></button>
					<button type="button" class="ccl-consent__button ccl-consent__button--primary" data-ccl-action="accept"><?php esc_html_e( 'Aceptar', 'wp-compliance-cl' ); ?></button>
				</div>
				<div class="ccl-consent__preferences" data-ccl-preferences hidden>
					<?php echo wp_kses_post( $this->preference_toggles() ); ?>
					<div class="ccl-consent__preference-actions"><button type="button" class="ccl-consent__button" data-ccl-action="withdraw"><?php esc_html_e( 'Retirar consentimiento', 'wp-compliance-cl' ); ?></button><button type="button" class="ccl-consent__button ccl-consent__button--primary" data-ccl-action="save"><?php esc_html_e( 'Guardar preferencias', 'wp-compliance-cl' ); ?></button></div>
				</div>
			</div>
		</div>
		<?php
	}

	private function preference_toggles(): string {
		$items = array(
			'necessary'  => array( 'Necesarias', 'Esenciales para funciones básicas, seguridad y preferencias de privacidad.', true ),
			'functional' => array( 'Funcionales', 'Habilitan funcionalidades no esenciales y una experiencia personalizada.', false ),
			'analytics'  => array( 'Analítica', 'Ayudan a comprender el uso del sitio y su rendimiento.', false ),
			'marketing'  => array( 'Marketing', 'Se utilizan para atribución, publicidad o seguimiento comercial.', false ),
		);
		$out = '<div class="ccl-preferences">';
		foreach ( $items as $key => $item ) {
			$out .= '<label class="ccl-pref"><span><strong>' . esc_html( $item[0] ) . '</strong><small>' . esc_html( $item[1] ) . '</small></span><input type="checkbox" data-ccl-category="' . esc_attr( $key ) . '" ' . ( $item[2] ? 'checked disabled' : '' ) . '><i></i></label>';
		}
		$out .= '</div>';
		return $out;
	}

	public function privacy_center(): string {
		$s = Util::settings();
		ob_start();
		?>
		<div class="ccl-privacy-center" style="--ccl-accent:<?php echo esc_attr( $s['accent_color'] ); ?>">
			<div>
				<span><?php esc_html_e( 'Centro de privacidad', 'wp-compliance-cl' ); ?></span>
				<h3><?php esc_html_e( 'Administra tus preferencias', 'wp-compliance-cl' ); ?></h3>
				<p><?php esc_html_e( 'Puedes revisar, modificar o retirar las preferencias almacenadas en este navegador.', 'wp-compliance-cl' ); ?></p>
			</div>
			<button type="button" class="ccl-privacy-center__button" data-ccl-open-preferences><?php esc_html_e( 'Cambiar preferencias', 'wp-compliance-cl' ); ?></button>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public function rights_form(): string {
		$s = Util::settings();
		$status = isset( $_GET['ccl_right'] ) ? sanitize_key( wp_unslash( $_GET['ccl_right'] ) ) : '';
		$ref    = isset( $_GET['ccl_ref'] ) ? sanitize_text_field( wp_unslash( $_GET['ccl_ref'] ) ) : '';
		ob_start();
		?>
		<div class="ccl-rights" style="--ccl-accent:<?php echo esc_attr( $s['accent_color'] ); ?>">
			<?php if ( 'ok' === $status ) : ?>
				<div class="ccl-rights__notice ccl-rights__notice--success"><strong><?php esc_html_e( 'Solicitud recibida.', 'wp-compliance-cl' ); ?></strong> <?php echo $ref ? esc_html( 'Tu referencia es ' . $ref . '.' ) : ''; ?> <?php esc_html_e( 'También recibirás un acuse por email.', 'wp-compliance-cl' ); ?></div>
			<?php elseif ( 'invalid' === $status ) : ?>
				<div class="ccl-rights__notice"><?php esc_html_e( 'Revisa los datos ingresados e intenta nuevamente.', 'wp-compliance-cl' ); ?></div>
			<?php elseif ( 'rate' === $status ) : ?>
				<div class="ccl-rights__notice"><?php esc_html_e( 'Ya recibimos una solicitud recientemente desde este email. Intenta nuevamente en unos minutos.', 'wp-compliance-cl' ); ?></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ccl-rights__form">
				<input type="hidden" name="action" value="ccl_submit_right">
				<?php wp_nonce_field( 'ccl_submit_right', 'ccl_right_nonce' ); ?>
				<div class="ccl-rights__honeypot" aria-hidden="true"><label>Empresa<input type="text" name="company" tabindex="-1" autocomplete="off"></label></div>
				<div class="ccl-rights__grid">
					<label><span><?php esc_html_e( 'Nombre', 'wp-compliance-cl' ); ?></span><input type="text" name="requester_name" required autocomplete="name"></label>
					<label><span><?php esc_html_e( 'Email', 'wp-compliance-cl' ); ?></span><input type="email" name="requester_email" required autocomplete="email"></label>
				</div>
				<label><span><?php esc_html_e( 'Derecho que deseas ejercer', 'wp-compliance-cl' ); ?></span><select name="right_type" required><option value="access">Acceso</option><option value="rectification">Rectificación</option><option value="deletion">Supresión</option><option value="objection">Oposición</option><option value="portability">Portabilidad</option><option value="blocking">Bloqueo</option></select></label>
				<label><span><?php esc_html_e( 'Detalles de la solicitud', 'wp-compliance-cl' ); ?></span><textarea name="details" rows="6" required placeholder="Describe de forma clara qué necesitas. Evita incluir información sensible innecesaria."></textarea></label>
				<p class="ccl-rights__help"><?php esc_html_e( 'Podemos solicitar información adicional únicamente cuando sea necesaria para verificar identidad o tramitar correctamente la solicitud.', 'wp-compliance-cl' ); ?></p>
				<button type="submit" class="ccl-rights__button"><?php esc_html_e( 'Enviar solicitud', 'wp-compliance-cl' ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
