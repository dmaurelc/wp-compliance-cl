<?php
namespace WPComplianceCL\Admin;

use WPComplianceCL\Core\Audit;
use WPComplianceCL\Core\Database;
use WPComplianceCL\Core\Documents;
use WPComplianceCL\Core\Rights;
use WPComplianceCL\Core\Scanner;
use WPComplianceCL\Core\Score;
use WPComplianceCL\Core\Util;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Admin {
	private string $capability;

	public function __construct() {
		$this->capability = Util::admin_capability();
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_ccl_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ccl_save_treatment', array( $this, 'save_treatment' ) );
		add_action( 'admin_post_ccl_delete_treatment', array( $this, 'delete_treatment' ) );
		add_action( 'admin_post_ccl_save_provider', array( $this, 'save_provider' ) );
		add_action( 'admin_post_ccl_delete_provider', array( $this, 'delete_provider' ) );
		add_action( 'admin_post_ccl_save_breach', array( $this, 'save_breach' ) );
		add_action( 'admin_post_ccl_delete_breach', array( $this, 'delete_breach' ) );
		add_action( 'admin_post_ccl_update_right', array( $this, 'update_right' ) );
		add_action( 'admin_post_ccl_generate_document', array( $this, 'generate_document' ) );
		add_action( 'admin_post_ccl_download_document', array( $this, 'download_document' ) );
		add_action( 'admin_post_ccl_run_scan', array( $this, 'run_scan' ) );
	}

	public function menu(): void {
		add_menu_page( 'WP Compliance CL', 'WP Compliance CL', $this->capability, 'wp-compliance-cl', array( $this, 'dashboard' ), 'dashicons-shield-alt', 58 );
		add_submenu_page( 'wp-compliance-cl', 'Dashboard', 'Dashboard', $this->capability, 'wp-compliance-cl', array( $this, 'dashboard' ) );
		add_submenu_page( 'wp-compliance-cl', 'Tratamientos', 'Tratamientos', $this->capability, 'ccl-treatments', array( $this, 'treatments' ) );
		add_submenu_page( 'wp-compliance-cl', 'Proveedores', 'Proveedores', $this->capability, 'ccl-providers', array( $this, 'providers' ) );
		add_submenu_page( 'wp-compliance-cl', 'Derechos', 'Derechos', $this->capability, 'ccl-rights', array( $this, 'rights' ) );
		add_submenu_page( 'wp-compliance-cl', 'Consentimientos', 'Consentimientos', $this->capability, 'ccl-consents', array( $this, 'consents' ) );
		add_submenu_page( 'wp-compliance-cl', 'Brechas', 'Brechas', $this->capability, 'ccl-breaches', array( $this, 'breaches' ) );
		add_submenu_page( 'wp-compliance-cl', 'Documentos', 'Documentos', $this->capability, 'ccl-documents', array( $this, 'documents' ) );
		add_submenu_page( 'wp-compliance-cl', 'Escáner', 'Escáner', $this->capability, 'ccl-scanner', array( $this, 'scanner' ) );
		add_submenu_page( 'wp-compliance-cl', 'Configuración', 'Configuración', $this->capability, 'ccl-settings', array( $this, 'settings' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'wp-compliance-cl' ) && false === strpos( $hook, 'ccl-' ) ) { return; }
		wp_enqueue_style( 'ccl-admin', WPCCL_URL . 'assets/css/admin.css', array(), WPCCL_VERSION );
		wp_enqueue_script( 'ccl-admin', WPCCL_URL . 'assets/js/admin.js', array(), WPCCL_VERSION, true );
	}

	private function guard(): void {
		if ( ! current_user_can( $this->capability ) ) { wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'wp-compliance-cl' ) ); }
	}

	private function page_start( string $title, string $description = '' ): void {
		$version = require WPCCL_DIR . 'src/Rules/Chile/Law21719/version.php';
		echo '<div class="wrap ccl-wrap"><div class="ccl-topbar"><div><div class="ccl-eyebrow">LEY 21.719 · PACK ' . esc_html( $version['pack_version'] ) . '</div><h1>' . esc_html( $title ) . '</h1>';
		if ( $description ) { echo '<p>' . esc_html( $description ) . '</p>'; }
		echo '</div><div class="ccl-topbar__meta"><span class="ccl-chip ccl-chip--soft">Vigencia: 01/12/2026</span><span class="ccl-chip">v' . esc_html( WPCCL_VERSION ) . '</span></div></div>';
		$health = Database::health();
		if ( ! $health['ok'] ) {
			echo '<div class="ccl-notice ccl-notice--error"><strong>Esquema de datos incompleto.</strong> No se encontraron: ' . esc_html( implode( ', ', $health['missing'] ) ) . '. WP Compliance CL intentará repararlo automáticamente.</div>';
		}
		$this->notice();
	}

	private function page_end(): void {
		echo '<div class="ccl-disclaimer">WP Compliance CL es una herramienta técnica y organizativa de apoyo. No constituye asesoría legal ni certificación de cumplimiento.</div></div>';
	}

	private function notice(): void {
		if ( empty( $_GET['ccl_notice'] ) ) { return; }
		$code = sanitize_key( wp_unslash( $_GET['ccl_notice'] ) );
		$messages = array( 'saved' => 'Cambios guardados.', 'deleted' => 'Registro eliminado.', 'generated' => 'Borrador/documento generado o actualizado.', 'scanned' => 'Escaneo completado.', 'responded' => 'Solicitud actualizada.' );
		if ( isset( $messages[ $code ] ) ) { echo '<div class="ccl-notice">' . esc_html( $messages[ $code ] ) . '</div>'; }
	}

	public function dashboard(): void {
		$this->guard();
		global $wpdb;
		$score = ( new Score() )->build();
		$rights_open = Database::exists( 'rights' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Database::table( 'rights' ) . " WHERE status NOT IN ('responded','closed','rejected')" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rights_due = Database::exists( 'rights' ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . Database::table( 'rights' ) . " WHERE status NOT IN ('responded','closed','rejected') AND due_at <= %s", gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ) ) ) : 0;
		$treatments = Database::exists( 'treatments' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'treatments' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$providers = Database::exists( 'providers' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'providers' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$breaches = Database::exists( 'breaches' ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Database::table( 'breaches' ) . " WHERE status='open'" ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$consents = Database::exists( 'consents' ) ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Database::table( 'consents' ) ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$this->page_start( 'Centro de cumplimiento', 'Una vista operativa de la postura del sitio frente a la Ley 21.719.' );
		echo '<div class="ccl-hero-grid"><section class="ccl-score-card"><div class="ccl-score-ring" style="--score:' . esc_attr( $score['percent'] ) . '"><div><strong>' . esc_html( $score['percent'] ) . '</strong><span>/100</span></div></div><div><span class="ccl-kicker">Score orientativo</span><h2>' . esc_html( $score['label'] ) . '</h2><p>Basado en controles técnicos y documentación registrada en este WordPress. Los controles no evaluados no suman puntaje.</p><div class="ccl-score-meta"><span><i class="ccl-mini-dot ccl-mini-dot--ok"></i>' . esc_html( $score['complete'] ) . ' completos</span><span><i class="ccl-mini-dot ccl-mini-dot--pending"></i>' . esc_html( $score['pending'] ) . ' pendientes</span><span><i class="ccl-mini-dot ccl-mini-dot--unknown"></i>' . esc_html( $score['unknown'] ) . ' no evaluados</span></div></div></section>';
		$severity_labels = array( 'high' => 'ALTA', 'medium' => 'MEDIA', 'low' => 'BAJA' );
		$next_action = 'unknown' === ( $score['next']['state'] ?? '' ) ? 'Evaluar ahora' : 'Resolver ahora';
		echo '<section class="ccl-card ccl-next"><div class="ccl-card__head"><div><span class="ccl-kicker">Siguiente prioridad</span><h2>' . esc_html( $score['next']['title'] ) . '</h2></div><span class="ccl-severity ccl-severity--' . esc_attr( $score['next']['severity'] ) . '">' . esc_html( $severity_labels[ $score['next']['severity'] ] ?? strtoupper( $score['next']['severity'] ) ) . '</span></div><p>' . esc_html( $score['next']['article'] ) . '</p><a class="ccl-button" href="' . esc_url( admin_url( 'admin.php?page=' . $score['next']['page'] ) ) . '">' . esc_html( $next_action ) . '</a></section></div>';

		echo '<div class="ccl-stats">';
		$this->stat( 'Tratamientos', $treatments, 'ccl-treatments' );
		$this->stat( 'Proveedores', $providers, 'ccl-providers' );
		$this->stat( 'Solicitudes abiertas', $rights_open, 'ccl-rights', $rights_due ? $rights_due . ' próximas/vencidas' : 'Sin urgencias' );
		$this->stat( 'Consentimientos', $consents, 'ccl-consents' );
		$this->stat( 'Brechas abiertas', $breaches, 'ccl-breaches' );
		echo '</div>';

		echo '<div class="ccl-layout"><section class="ccl-card"><div class="ccl-card__head"><div><span class="ccl-kicker">Controles</span><h2>Mapa de cumplimiento</h2></div></div><div class="ccl-control-list">';
		foreach ( $score['controls'] as $control ) {
			echo '<div class="ccl-control"><span class="ccl-status-dot ccl-status-dot--' . esc_attr( $control['state'] ) . '"></span><div><strong>' . esc_html( $control['title'] ) . '</strong><small>' . esc_html( $control['article'] ) . '</small></div><span class="ccl-control__state">' . esc_html( $control['state_label'] ) . '</span></div>';
		}
		echo '</div></section>';

		$recent = Database::exists( 'audit' ) ? $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'audit' ) . ' ORDER BY id DESC LIMIT 8' ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<section class="ccl-card"><div class="ccl-card__head"><div><span class="ccl-kicker">Evidencia</span><h2>Actividad reciente</h2></div></div><div class="ccl-timeline">';
		if ( $recent ) { foreach ( $recent as $row ) { echo '<div><span></span><p><strong>' . esc_html( $row->action ) . '</strong><small>' . esc_html( get_date_from_gmt( $row->created_at, 'd/m/Y H:i' ) ) . '</small></p></div>'; } } else { echo '<div class="ccl-empty">Aún no hay eventos de auditoría.</div>'; }
		echo '</div></section></div>';
		$this->page_end();
	}

	private function stat( string $label, int $value, string $page, string $note = '' ): void {
		echo '<a class="ccl-stat" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( number_format_i18n( $value ) ) . '</strong><small>' . esc_html( $note ?: 'Gestionar' ) . '</small></a>';
	}


	public function settings(): void {
		$this->guard(); $s = Util::settings(); $this->page_start( 'Configuración', 'Datos del responsable, seguridad y comportamiento del módulo de consentimiento.' );
		echo '<form class="ccl-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccl_save_settings">'; wp_nonce_field( 'ccl_save_settings' );
		echo '<div class="ccl-form-grid"><section class="ccl-card"><div class="ccl-card__head"><div><span class="ccl-kicker">Organización</span><h2>Responsable del tratamiento</h2></div></div>';
		$this->input( 'organisation_name', 'Razón social / nombre', $s['organisation_name'], true ); $this->input( 'rut', 'RUT', $s['rut'] ); $this->input( 'representative', 'Representante legal', $s['representative'] ); $this->input( 'address', 'Domicilio', $s['address'] ); $this->input( 'privacy_email', 'Email de privacidad', $s['privacy_email'], true, 'email' ); $this->input( 'dpo_name', 'Delegado / responsable interno (opcional)', $s['dpo_name'] ); $this->input( 'dpo_email', 'Email del delegado (opcional)', $s['dpo_email'], false, 'email' ); echo '</section>';
		echo '<section class="ccl-card"><div class="ccl-card__head"><div><span class="ccl-kicker">Privacidad</span><h2>Consentimiento y seguridad</h2></div></div>';
		$this->checkbox( 'consent_enabled', 'Mostrar banner de preferencias', ! empty( $s['consent_enabled'] ) ); $this->input( 'consent_version', 'Versión del aviso/consentimiento', $s['consent_version'], true ); $this->input( 'accent_color', 'Color principal', $s['accent_color'], true, 'color' );
		$this->textarea( 'security_measures', 'Medidas de seguridad documentadas', $s['security_measures'], 'Ej.: MFA administradores, backups cifrados, HTTPS, control de acceso, actualizaciones, logging...' ); $this->checkbox( 'breach_procedure_ready', 'Existe un procedimiento interno de respuesta a vulneraciones', ! empty( $s['breach_procedure_ready'] ) );
		$this->textarea( 'script_rules', 'Reglas de bloqueo de scripts', $s['script_rules'], "Un handle por línea: google-analytics=analytics\nmeta-pixel=marketing" ); echo '<p class="ccl-help">Se aplica a scripts registrados mediante wp_enqueue_script(). Categorías: necessary, functional, analytics, marketing.</p></section></div><div class="ccl-form-actions"><button class="ccl-button ccl-button--primary">Guardar configuración</button></div></form>';
		$this->page_end();
	}

	private function input( string $name, string $label, $value, bool $required = false, string $type = 'text' ): void { echo '<label class="ccl-field"><span>' . esc_html( $label ) . '</span><input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . ( $required ? 'required' : '' ) . '></label>'; }
	private function textarea( string $name, string $label, $value, string $placeholder = '' ): void { echo '<label class="ccl-field"><span>' . esc_html( $label ) . '</span><textarea name="' . esc_attr( $name ) . '" rows="5" placeholder="' . esc_attr( $placeholder ) . '">' . esc_textarea( $value ) . '</textarea></label>'; }
	private function checkbox( string $name, string $label, bool $checked ): void { echo '<label class="ccl-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( $checked, true, false ) . '><span><strong>' . esc_html( $label ) . '</strong></span></label>'; }

	public function save_settings(): void {
		$this->guard(); check_admin_referer( 'ccl_save_settings' );
		$fields = array( 'organisation_name','rut','address','representative','privacy_email','dpo_name','dpo_email','consent_version','accent_color','security_measures','script_rules' ); $new = Util::settings();
		foreach ( $fields as $field ) { $new[ $field ] = isset( $_POST[ $field ] ) ? ( in_array( $field, array( 'security_measures','script_rules' ), true ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : ''; }
		$new['privacy_email'] = sanitize_email( $new['privacy_email'] ); $new['dpo_email'] = sanitize_email( $new['dpo_email'] ); $new['consent_enabled'] = ! empty( $_POST['consent_enabled'] ) ? 1 : 0; $new['breach_procedure_ready'] = ! empty( $_POST['breach_procedure_ready'] ) ? 1 : 0; $new['accent_color'] = sanitize_hex_color( $new['accent_color'] ) ?: '#1f6f5c';
		update_option( 'ccl_settings', $new, false ); Audit::log( 'settings_updated', 'settings' ); $this->redirect( 'ccl-settings', 'saved' );
	}

	public function treatments(): void {
		$this->guard(); global $wpdb; $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; $edit = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Database::table( 'treatments' ) . ' WHERE id=%d', $id ) ) : null;
		$this->page_start( 'Tratamientos', 'Inventario operativo de finalidades, datos, bases de licitud, retención y factores de riesgo.' );
		echo '<div class="ccl-toolbar"><a class="ccl-button ccl-button--primary" href="' . esc_url( admin_url( 'admin.php?page=ccl-treatments&view=edit' ) ) . '">+ Nuevo tratamiento</a></div>';
		if ( isset( $_GET['view'] ) && 'edit' === $_GET['view'] ) { $this->treatment_form( $edit ); } else { $rows = $wpdb->get_results( 'SELECT * FROM ' . Database::table( 'treatments' ) . ' ORDER BY updated_at DESC' ); $this->table_treatments( $rows ); }
		$this->page_end();
	}

	private function treatment_form( $row ): void {
		$v = static fn( $k, $d='' ) => $row && isset( $row->$k ) ? $row->$k : $d;
		echo '<section class="ccl-card"><form class="ccl-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="ccl_save_treatment"><input type="hidden" name="id" value="' . esc_attr( $v( 'id', 0 ) ) . '">'; wp_nonce_field( 'ccl_save_treatment' );
		echo '<div class="ccl-form-grid"> <div>'; $this->input( 'name','Nombre del tratamiento',$v('name'),true ); $this->textarea('purpose','Finalidad',$v('purpose')); $this->textarea('data_categories','Categorías de datos',$v('data_categories'),'Nombre, email, teléfono...'); $this->textarea('data_subjects','Categorías de titulares',$v('data_subjects'),'Clientes, prospectos, trabajadores...'); echo '</div><div>';
		echo '<label class="ccl-field"><span>Base de licitud</span><select name="lawful_basis" required>'; foreach ( array('consent'=>'Consentimiento','contract'=>'Contrato / medidas precontractuales','legal_obligation'=>'Obligación legal','legitimate_interest'=>'Interés legítimo','other'=>'Otra / revisar') as $k=>$label ) { echo '<option value="'.esc_attr($k).'" '.selected($v('lawful_basis'),$k,false).'>'.esc_html($label).'</option>'; } echo '</select></label>';
		$this->input('retention','Plazo / criterio de retención',$v('retention')); $this->textarea('recipients','Destinatarios',$v('recipients')); echo '<div class="ccl-check-grid">'; $this->checkbox('sensitive','Datos sensibles',(bool)$v('sensitive')); $this->checkbox('large_scale','Gran escala',(bool)$v('large_scale')); $this->checkbox('automated_decisions','Decisiones automatizadas significativas',(bool)$v('automated_decisions')); $this->checkbox('public_monitoring','Monitorización sistemática de espacios públicos',(bool)$v('public_monitoring')); echo '</div><label class="ccl-field"><span>Estado EIPD</span><select name="dpia_status"><option value="not_assessed" '.selected($v('dpia_status','not_assessed'),'not_assessed',false).'>No evaluada</option><option value="not_required" '.selected($v('dpia_status'),'not_required',false).'>No requerida según evaluación interna</option><option value="in_progress" '.selected($v('dpia_status'),'in_progress',false).'>En proceso</option><option value="completed" '.selected($v('dpia_status'),'completed',false).'>Completada</option></select></label></div></div><div class="ccl-form-actions"><a class="ccl-button" href="'.esc_url(admin_url('admin.php?page=ccl-treatments')).'">Cancelar</a><button class="ccl-button ccl-button--primary">Guardar tratamiento</button></div></form></section>';
	}

	private function table_treatments( array $rows ): void { echo '<section class="ccl-card ccl-table-card"><table class="ccl-table"><thead><tr><th>Tratamiento</th><th>Base</th><th>Retención</th><th>Riesgo</th><th></th></tr></thead><tbody>'; if(!$rows){echo '<tr><td colspan="5" class="ccl-empty">Aún no hay tratamientos registrados.</td></tr>';} foreach($rows as $r){$risk=($r->sensitive||$r->large_scale||$r->automated_decisions||$r->public_monitoring)?'Alto / revisar':'Estándar'; echo '<tr><td><strong>'.esc_html($r->name).'</strong><small>'.esc_html(wp_trim_words($r->purpose,12)).'</small></td><td><span class="ccl-chip ccl-chip--soft">'.esc_html($r->lawful_basis).'</span></td><td>'.esc_html($r->retention?:'Pendiente').'</td><td>'.esc_html($risk).'</td><td class="ccl-actions"><a href="'.esc_url(admin_url('admin.php?page=ccl-treatments&view=edit&id='.$r->id)).'">Editar</a><a class="ccl-danger" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccl_delete_treatment&id='.$r->id),'ccl_delete_treatment_'.$r->id)).'" data-ccl-confirm="¿Eliminar este tratamiento?">Eliminar</a></td></tr>'; } echo '</tbody></table></section>'; }

	public function save_treatment(): void { $this->guard(); check_admin_referer('ccl_save_treatment'); global $wpdb; $id=isset($_POST['id'])?absint($_POST['id']):0; $now=Util::now_mysql(); $data=array('name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'purpose'=>sanitize_textarea_field(wp_unslash($_POST['purpose']??'')),'data_categories'=>sanitize_textarea_field(wp_unslash($_POST['data_categories']??'')),'data_subjects'=>sanitize_textarea_field(wp_unslash($_POST['data_subjects']??'')),'lawful_basis'=>sanitize_key(wp_unslash($_POST['lawful_basis']??'')),'retention'=>sanitize_text_field(wp_unslash($_POST['retention']??'')),'recipients'=>sanitize_textarea_field(wp_unslash($_POST['recipients']??'')),'sensitive'=>!empty($_POST['sensitive'])?1:0,'large_scale'=>!empty($_POST['large_scale'])?1:0,'automated_decisions'=>!empty($_POST['automated_decisions'])?1:0,'public_monitoring'=>!empty($_POST['public_monitoring'])?1:0,'dpia_status'=>sanitize_key(wp_unslash($_POST['dpia_status']??'not_assessed')),'status'=>'active','updated_at'=>$now); if($id){$wpdb->update(Database::table('treatments'),$data,array('id'=>$id));}else{$data['created_at']=$now;$wpdb->insert(Database::table('treatments'),$data);$id=(int)$wpdb->insert_id;} Audit::log('treatment_saved','treatment',(string)$id); $this->redirect('ccl-treatments','saved'); }
	public function delete_treatment(): void { $this->guard(); $id=absint($_GET['id']??0); check_admin_referer('ccl_delete_treatment_'.$id); global $wpdb; $wpdb->delete(Database::table('treatments'),array('id'=>$id),array('%d')); Audit::log('treatment_deleted','treatment',(string)$id); $this->redirect('ccl-treatments','deleted'); }

	public function providers(): void {
		$this->guard(); global $wpdb; $id=isset($_GET['id'])?absint($_GET['id']):0; $edit=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('providers').' WHERE id=%d',$id)):null; $this->page_start('Proveedores y encargados','Registra terceros, transferencias, subencargados y estado contractual.'); echo '<div class="ccl-toolbar"><a class="ccl-button ccl-button--primary" href="'.esc_url(admin_url('admin.php?page=ccl-providers&view=edit')).'">+ Nuevo proveedor</a></div>'; if(isset($_GET['view'])&&'edit'===$_GET['view']){$this->provider_form($edit);}else{$rows=$wpdb->get_results('SELECT * FROM '.Database::table('providers').' ORDER BY updated_at DESC');$this->table_providers($rows);} $this->page_end();
	}
	private function provider_form($row): void { $v=static fn($k,$d='')=>$row&&isset($row->$k)?$row->$k:$d; echo '<section class="ccl-card"><form class="ccl-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ccl_save_provider"><input type="hidden" name="id" value="'.esc_attr($v('id',0)).'">'; wp_nonce_field('ccl_save_provider'); echo '<div class="ccl-form-grid"><div>'; $this->input('name','Proveedor',$v('name'),true); $this->input('service','Servicio',$v('service')); echo '<label class="ccl-field"><span>Rol</span><select name="role"><option value="processor" '.selected($v('role','processor'),'processor',false).'>Encargado / processor</option><option value="controller" '.selected($v('role'),'controller',false).'>Responsable independiente</option><option value="joint" '.selected($v('role'),'joint',false).'>Corresponsable</option></select></label>'; $this->textarea('data_categories','Datos tratados',$v('data_categories')); $this->textarea('purpose','Finalidad',$v('purpose')); echo '</div><div>'; $this->input('country','País / región de tratamiento',$v('country')); $this->textarea('subprocessors','Subencargados',$v('subprocessors')); $this->checkbox('international_transfer','Existe transferencia internacional',(bool)$v('international_transfer')); $this->input('transfer_mechanism','Mecanismo / salvaguarda documentada',$v('transfer_mechanism')); echo '<label class="ccl-field"><span>DPA / contrato</span><select name="dpa_status"><option value="unknown" '.selected($v('dpa_status','unknown'),'unknown',false).'>Por revisar</option><option value="pending" '.selected($v('dpa_status'),'pending',false).'>Pendiente</option><option value="signed" '.selected($v('dpa_status'),'signed',false).'>Firmado / vigente</option><option value="not_applicable" '.selected($v('dpa_status'),'not_applicable',false).'>No aplica</option></select></label>'; $this->input('document_url','URL de contrato/documentación',$v('document_url'),false,'url'); echo '</div></div><div class="ccl-form-actions"><a class="ccl-button" href="'.esc_url(admin_url('admin.php?page=ccl-providers')).'">Cancelar</a><button class="ccl-button ccl-button--primary">Guardar proveedor</button></div></form></section>'; }
	private function table_providers(array $rows): void { echo '<section class="ccl-card ccl-table-card"><table class="ccl-table"><thead><tr><th>Proveedor</th><th>Rol</th><th>País</th><th>Transferencia</th><th>DPA</th><th></th></tr></thead><tbody>'; if(!$rows){echo '<tr><td colspan="6" class="ccl-empty">Aún no hay proveedores registrados.</td></tr>';} foreach($rows as $r){echo '<tr><td><strong>'.esc_html($r->name).'</strong><small>'.esc_html($r->service).'</small></td><td>'.esc_html($r->role).'</td><td>'.esc_html($r->country?:'—').'</td><td>'.($r->international_transfer?'<span class="ccl-chip ccl-chip--warn">Sí</span>':'No').'</td><td>'.esc_html($r->dpa_status).'</td><td class="ccl-actions"><a href="'.esc_url(admin_url('admin.php?page=ccl-providers&view=edit&id='.$r->id)).'">Editar</a><a class="ccl-danger" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccl_delete_provider&id='.$r->id),'ccl_delete_provider_'.$r->id)).'" data-ccl-confirm="¿Eliminar este proveedor?">Eliminar</a></td></tr>'; } echo '</tbody></table></section>'; }
	public function save_provider(): void { $this->guard(); check_admin_referer('ccl_save_provider'); global $wpdb; $id=absint($_POST['id']??0); $now=Util::now_mysql(); $data=array('name'=>sanitize_text_field(wp_unslash($_POST['name']??'')),'service'=>sanitize_text_field(wp_unslash($_POST['service']??'')),'role'=>sanitize_key(wp_unslash($_POST['role']??'processor')),'data_categories'=>sanitize_textarea_field(wp_unslash($_POST['data_categories']??'')),'purpose'=>sanitize_textarea_field(wp_unslash($_POST['purpose']??'')),'country'=>sanitize_text_field(wp_unslash($_POST['country']??'')),'subprocessors'=>sanitize_textarea_field(wp_unslash($_POST['subprocessors']??'')),'international_transfer'=>!empty($_POST['international_transfer'])?1:0,'transfer_mechanism'=>sanitize_text_field(wp_unslash($_POST['transfer_mechanism']??'')),'dpa_status'=>sanitize_key(wp_unslash($_POST['dpa_status']??'unknown')),'document_url'=>esc_url_raw(wp_unslash($_POST['document_url']??'')),'updated_at'=>$now); if($id){$wpdb->update(Database::table('providers'),$data,array('id'=>$id));}else{$data['created_at']=$now;$wpdb->insert(Database::table('providers'),$data);$id=(int)$wpdb->insert_id;} Audit::log('provider_saved','provider',(string)$id); $this->redirect('ccl-providers','saved'); }
	public function delete_provider(): void { $this->guard(); $id=absint($_GET['id']??0); check_admin_referer('ccl_delete_provider_'.$id); global $wpdb; $wpdb->delete(Database::table('providers'),array('id'=>$id),array('%d')); Audit::log('provider_deleted','provider',(string)$id); $this->redirect('ccl-providers','deleted'); }

	public function rights(): void { $this->guard(); global $wpdb; $id=absint($_GET['id']??0); $this->page_start('Derechos de titulares','Expedientes de acceso, rectificación, supresión, oposición, portabilidad y bloqueo.'); if($id){$this->right_detail($id);}else{$rows=$wpdb->get_results('SELECT * FROM '.Database::table('rights').' ORDER BY received_at DESC'); echo '<section class="ccl-card ccl-table-card"><table class="ccl-table"><thead><tr><th>Referencia</th><th>Solicitante</th><th>Derecho</th><th>Estado</th><th>Vence</th><th></th></tr></thead><tbody>'; if(!$rows){echo '<tr><td colspan="6" class="ccl-empty">No hay solicitudes todavía. Publica el canal desde Documentos.</td></tr>';} foreach($rows as $r){$over=strtotime($r->due_at)<time()&&!in_array($r->status,array('responded','closed','rejected'),true); echo '<tr><td><strong>'.esc_html($r->reference).'</strong></td><td>'.esc_html($r->requester_name).'<small>'.esc_html($r->requester_email).'</small></td><td>'.esc_html(Util::right_label($r->right_type)).'</td><td><span class="ccl-chip">'.esc_html(Util::status_label($r->status)).'</span></td><td class="'.($over?'ccl-text-danger':'').'">'.esc_html(get_date_from_gmt($r->due_at,'d/m/Y')).'</td><td><a href="'.esc_url(admin_url('admin.php?page=ccl-rights&id='.$r->id)).'">Abrir</a></td></tr>'; } echo '</tbody></table></section>'; } $this->page_end(); }
	private function right_detail(int $id): void { global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('rights').' WHERE id=%d',$id)); if(!$r){echo '<div class="ccl-empty">Solicitud no encontrada.</div>';return;} $events=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Database::table('right_events').' WHERE right_id=%d ORDER BY id DESC',$id)); echo '<div class="ccl-layout"><section class="ccl-card"><span class="ccl-kicker">'.esc_html($r->reference).'</span><h2>'.esc_html(Util::right_label($r->right_type)).'</h2><div class="ccl-meta-list"><div><span>Solicitante</span><strong>'.esc_html($r->requester_name).'</strong><small>'.esc_html($r->requester_email).'</small></div><div><span>Recibida</span><strong>'.esc_html(get_date_from_gmt($r->received_at,'d/m/Y H:i')).'</strong></div><div><span>Fecha límite</span><strong>'.esc_html(get_date_from_gmt($r->due_at,'d/m/Y')).'</strong></div></div><div class="ccl-request-details">'.nl2br(esc_html($r->details)).'</div></section><section class="ccl-card"><form class="ccl-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ccl_update_right"><input type="hidden" name="id" value="'.esc_attr($id).'">'; wp_nonce_field('ccl_update_right_'.$id); echo '<label class="ccl-field"><span>Estado</span><select name="status">'; foreach(array('received','verified','in_progress','responded','closed','rejected') as $st){echo '<option value="'.esc_attr($st).'" '.selected($r->status,$st,false).'>'.esc_html(Util::status_label($st)).'</option>';} echo '</select></label>'; $this->checkbox('identity_verified','Identidad verificada por el responsable',(bool)$r->identity_verified); if(empty($r->extended_at)){ $this->checkbox('extend_due','Aplicar prórroga única de 30 días',false); } $this->textarea('response','Respuesta / registro de actuación',$r->response?:'','Escribe la respuesta. Marca “Enviar respuesta” para remitirla por email.'); $this->checkbox('send_response','Enviar esta respuesta por email al titular',false); echo '<div class="ccl-form-actions"><a class="ccl-button" href="'.esc_url(admin_url('admin.php?page=ccl-rights')).'">Volver</a><button class="ccl-button ccl-button--primary">Guardar expediente</button></div></form></section></div><section class="ccl-card"><h2>Historial</h2><div class="ccl-timeline">'; foreach($events as $e){echo '<div><span></span><p><strong>'.esc_html($e->event_type).'</strong><small>'.esc_html(get_date_from_gmt($e->created_at,'d/m/Y H:i')).' · '.esc_html($e->message).'</small></p></div>';} echo '</div></section>'; }
	public function update_right(): void { $this->guard(); $id=absint($_POST['id']??0); check_admin_referer('ccl_update_right_'.$id); global $wpdb; $r=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('rights').' WHERE id=%d',$id)); if(!$r){wp_die('Solicitud no encontrada');} $status=sanitize_key(wp_unslash($_POST['status']??'received')); $response=sanitize_textarea_field(wp_unslash($_POST['response']??'')); $data=array('status'=>$status,'identity_verified'=>!empty($_POST['identity_verified'])?1:0,'response'=>$response); $rights=new Rights(); if(!empty($_POST['extend_due'])&&empty($r->extended_at)){ $data['extended_at']=Util::now_mysql(); $data['due_at']=gmdate('Y-m-d H:i:s',strtotime($r->due_at.' UTC')+30*DAY_IN_SECONDS); $rights->event($id,'extended','Prórroga única de 30 días aplicada.'); } if(!empty($_POST['send_response'])&&$response){ $data['responded_at']=Util::now_mysql(); $data['status']='responded'; wp_mail($r->requester_email,'['.$r->reference.'] Respuesta a tu solicitud de datos',$response."\n\nReferencia: ".$r->reference); $rights->event($id,'response_sent','Respuesta enviada por email al titular.'); } $wpdb->update(Database::table('rights'),$data,array('id'=>$id)); $rights->event($id,'updated','Expediente actualizado a estado '.$data['status'].'.'); Audit::log('right_updated','right',(string)$id,array('status'=>$data['status'])); $this->redirect('ccl-rights&id='.$id,'responded'); }

	public function consents(): void { $this->guard(); global $wpdb; $rows=$wpdb->get_results('SELECT * FROM '.Database::table('consents').' ORDER BY id DESC LIMIT 250'); $this->page_start('Consentimientos','Registro append-only de elecciones realizadas en el banner y centro de privacidad.'); echo '<section class="ccl-card ccl-table-card"><table class="ccl-table"><thead><tr><th>UUID</th><th>Versión</th><th>Categorías</th><th>Estado</th><th>Fecha</th><th>Prueba</th></tr></thead><tbody>'; if(!$rows){echo '<tr><td colspan="6" class="ccl-empty">Aún no se han registrado consentimientos.</td></tr>';} foreach($rows as $r){$cats=json_decode($r->categories,true); echo '<tr><td><code>'.esc_html($r->uuid).'</code></td><td>'.esc_html($r->consent_version).'</td><td>'.esc_html(implode(', ',(array)$cats)).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html(get_date_from_gmt($r->created_at,'d/m/Y H:i')).'</td><td><code title="'.esc_attr($r->proof_hash).'">'.esc_html(substr($r->proof_hash,0,12)).'…</code></td></tr>'; } echo '</tbody></table></section>'; $this->page_end(); }

	public function breaches(): void { $this->guard(); global $wpdb; $id=absint($_GET['id']??0); $edit=$id?$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Database::table('breaches').' WHERE id=%d',$id)):null; $this->page_start('Vulneraciones de seguridad','Registro operativo alineado con el art. 14 sexies. No se codifica un plazo fijo de 72 horas.'); echo '<div class="ccl-toolbar"><a class="ccl-button ccl-button--primary" href="'.esc_url(admin_url('admin.php?page=ccl-breaches&view=edit')).'">+ Registrar vulneración</a></div>'; if(isset($_GET['view'])&&'edit'===$_GET['view']){$this->breach_form($edit);}else{$rows=$wpdb->get_results('SELECT * FROM '.Database::table('breaches').' ORDER BY detected_at DESC'); echo '<section class="ccl-card ccl-table-card"><table class="ccl-table"><thead><tr><th>Incidente</th><th>Detectado</th><th>Riesgo</th><th>Agencia</th><th>Titulares</th><th></th></tr></thead><tbody>'; if(!$rows){echo '<tr><td colspan="6" class="ccl-empty">No hay vulneraciones registradas.</td></tr>';} foreach($rows as $r){echo '<tr><td><strong>'.esc_html($r->title).'</strong><small>'.esc_html(wp_trim_words($r->nature,12)).'</small></td><td>'.esc_html(get_date_from_gmt($r->detected_at,'d/m/Y H:i')).'</td><td>'.esc_html($r->risk_level).'</td><td>'.($r->notified_agency?'Sí':'No').'</td><td>'.($r->notified_subjects?'Sí':'No').'</td><td class="ccl-actions"><a href="'.esc_url(admin_url('admin.php?page=ccl-breaches&view=edit&id='.$r->id)).'">Editar</a><a class="ccl-danger" data-ccl-confirm="¿Eliminar este registro?" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccl_delete_breach&id='.$r->id),'ccl_delete_breach_'.$r->id)).'">Eliminar</a></td></tr>'; } echo '</tbody></table></section>'; } $this->page_end(); }
	private function breach_form($row): void { $v=static fn($k,$d='')=>$row&&isset($row->$k)?$row->$k:$d; echo '<section class="ccl-card"><form class="ccl-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="ccl_save_breach"><input type="hidden" name="id" value="'.esc_attr($v('id',0)).'">'; wp_nonce_field('ccl_save_breach'); echo '<div class="ccl-form-grid"><div>'; $this->input('title','Título del incidente',$v('title'),true); $this->input('detected_at','Fecha/hora detectada', $v('detected_at')?gmdate('Y-m-d\TH:i',strtotime($v('detected_at').' UTC')):gmdate('Y-m-d\TH:i'),true,'datetime-local'); $this->textarea('nature','Naturaleza de la vulneración',$v('nature')); $this->textarea('data_categories','Datos afectados',$v('data_categories')); $this->input('affected_estimate','Titulares afectados (estimación)',$v('affected_estimate',0),false,'number'); echo '</div><div>'; $this->textarea('effects','Efectos y posibles consecuencias',$v('effects')); echo '<label class="ccl-field"><span>Nivel de riesgo</span><select name="risk_level">'; foreach(array('pending'=>'Pendiente','low'=>'Bajo','medium'=>'Medio','high'=>'Alto','critical'=>'Crítico') as $k=>$l){echo '<option value="'.$k.'" '.selected($v('risk_level','pending'),$k,false).'>'.$l.'</option>';} echo '</select></label>'; $this->textarea('measures','Medidas adoptadas',$v('measures')); $this->checkbox('notified_agency','Comunicación a la Agencia registrada',(bool)$v('notified_agency')); $this->checkbox('notified_subjects','Comunicación a titulares registrada',(bool)$v('notified_subjects')); $this->textarea('evidence','Evidencias / referencias',$v('evidence')); echo '</div></div><div class="ccl-form-actions"><a class="ccl-button" href="'.esc_url(admin_url('admin.php?page=ccl-breaches')).'">Cancelar</a><button class="ccl-button ccl-button--primary">Guardar vulneración</button></div></form></section>'; }
	public function save_breach(): void { $this->guard(); check_admin_referer('ccl_save_breach'); global $wpdb; $id=absint($_POST['id']??0); $now=Util::now_mysql(); $local=sanitize_text_field(wp_unslash($_POST['detected_at']??'')); $detected=gmdate('Y-m-d H:i:s',strtotime($local.' '.wp_timezone_string())); $data=array('title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'detected_at'=>$detected,'nature'=>sanitize_textarea_field(wp_unslash($_POST['nature']??'')),'data_categories'=>sanitize_textarea_field(wp_unslash($_POST['data_categories']??'')),'affected_estimate'=>absint($_POST['affected_estimate']??0),'effects'=>sanitize_textarea_field(wp_unslash($_POST['effects']??'')),'risk_level'=>sanitize_key(wp_unslash($_POST['risk_level']??'pending')),'measures'=>sanitize_textarea_field(wp_unslash($_POST['measures']??'')),'notified_agency'=>!empty($_POST['notified_agency'])?1:0,'notified_subjects'=>!empty($_POST['notified_subjects'])?1:0,'evidence'=>sanitize_textarea_field(wp_unslash($_POST['evidence']??'')),'status'=>'open','updated_at'=>$now); if($id){$wpdb->update(Database::table('breaches'),$data,array('id'=>$id));}else{$data['created_at']=$now;$wpdb->insert(Database::table('breaches'),$data);$id=(int)$wpdb->insert_id;} Audit::log('breach_saved','breach',(string)$id); $this->redirect('ccl-breaches','saved'); }
	public function delete_breach(): void { $this->guard(); $id=absint($_GET['id']??0); check_admin_referer('ccl_delete_breach_'.$id); global $wpdb; $wpdb->delete(Database::table('breaches'),array('id'=>$id),array('%d')); Audit::log('breach_deleted','breach',(string)$id); $this->redirect('ccl-breaches','deleted'); }

	public function documents(): void {
		$this->guard();
		$s    = Util::settings();
		$docs = new Documents();
		$this->page_start( 'Documentos y páginas', 'Genera borradores operativos desde la configuración e inventario actuales.' );

		$existing = $docs->existing_privacy_pages();
		if ( $existing ) {
			echo '<section class="ccl-card ccl-alert-card"><span class="ccl-kicker">Evitar duplicados</span><h2>Ya existe contenido legal relacionado con privacidad</h2><p>WP Compliance CL no lo reemplazará automáticamente. Revisa estas páginas antes de crear otra política:</p><div class="ccl-existing-pages">';
			foreach ( $existing as $page ) {
				echo '<a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '"><strong>' . esc_html( $page->post_title ) . '</strong><small>' . esc_html( ucfirst( $page->post_status ) ) . ' · #' . esc_html( $page->ID ) . '</small></a>';
			}
			echo '</div></section>';
		}

		echo '<div class="ccl-doc-grid">';
		$this->doc_card( 'policy', 'Política de tratamiento / privacidad', 'Art. 14 ter', 'Genera o actualiza una página con responsable, tratamientos, proveedores, derechos y versionado.', (int) $s['policy_page_id'], $docs->readiness( 'policy' ), ! empty( $existing ) );
		$this->doc_card( 'cookies', 'Política de tecnologías y preferencias', 'Consentimiento / transparencia', 'Explica categorías y enlaza con el centro de privacidad.', (int) $s['cookie_page_id'], $docs->readiness( 'cookies' ) );
		$this->doc_card( 'rights', 'Canal de derechos', 'Arts. 4 y 11', 'Crea una página con el formulario público ARCO+ y referencia de expediente.', (int) $s['rights_page_id'], $docs->readiness( 'rights' ) );
		echo '</div><div class="ccl-doc-grid">';
		$this->download_card( 'rat', 'Inventario / RAT', 'Inventario interno', 'Exporta los tratamientos registrados en Markdown.' );
		$this->download_card( 'dpa', 'Modelo DPA', 'Art. 15 bis', 'Borrador operativo para acuerdos con encargados.' );
		$this->download_card( 'transfers', 'Anexo de transferencias', 'Transferencias internacionales', 'Consolida proveedores y mecanismos registrados.' );
		$this->download_card( 'breach-plan', 'Plan de respuesta a brechas', 'Art. 14 sexies', 'Runbook interno sin asumir un plazo fijo de 72 horas.' );
		$this->download_card( 'dpia', 'Evaluación preliminar EIPD', 'Art. 15 ter', 'Consolida tratamientos con factores de alto riesgo.' );
		echo '</div>';

		if ( $s['policy_page_id'] ) {
			echo '<section class="ccl-card"><span class="ccl-kicker">Vista previa</span><h2>Política generada</h2><div class="ccl-document-preview">' . wp_kses_post( $docs->policy_content() ) . '</div></section>';
		}
		$this->page_end();
	}

	private function doc_card( string $type, string $title, string $article, string $desc, int $page_id, array $readiness, bool $privacy_conflict = false ): void {
		echo '<section class="ccl-card ccl-doc"><span class="ccl-kicker">' . esc_html( $article ) . '</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $desc ) . '</p>';

		if ( $page_id && get_post( $page_id ) ) {
			$status = get_post_status( $page_id );
			echo '<div class="ccl-doc__status"><span class="ccl-status-dot ccl-status-dot--ok"></span> Página #' . esc_html( $page_id ) . ' · ' . esc_html( ucfirst( (string) $status ) ) . '</div><a class="ccl-button" target="_blank" rel="noopener" href="' . esc_url( get_permalink( $page_id ) ) . '">Ver página</a> ';
		}

		if ( ! $readiness['ready'] ) {
			echo '<div class="ccl-readiness"><strong>Antes de publicar:</strong><ul>';
			foreach ( $readiness['missing'] as $item ) {
				echo '<li>' . esc_html( $item ) . '</li>';
			}
			echo '</ul></div>';
		}

		$args = 'admin-post.php?action=ccl_generate_document&type=' . rawurlencode( $type );
		$label = $page_id ? 'Actualizar contenido' : 'Generar borrador';
		if ( $privacy_conflict && ! $page_id && 'policy' === $type ) {
			$args .= '&force=1';
			$label = 'Crear borrador igualmente';
			echo '<p class="ccl-help">Se detectó otra página de privacidad. Esta acción crea una nueva solo porque tú lo confirmas explícitamente.</p>';
		}
		echo '<a class="ccl-button ccl-button--primary" href="' . esc_url( wp_nonce_url( admin_url( $args ), 'ccl_generate_document_' . $type ) ) . '">' . esc_html( $label ) . '</a></section>';
	}

	private function download_card( string $type, string $title, string $article, string $desc ): void {
		echo '<section class="ccl-card ccl-doc"><span class="ccl-kicker">' . esc_html( $article ) . '</span><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $desc ) . '</p><a class="ccl-button ccl-button--primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ccl_download_document&type=' . $type ), 'ccl_download_document_' . $type ) ) . '">Descargar Markdown</a></section>';
	}

	public function generate_document(): void {
		$this->guard();
		$type  = sanitize_key( $_GET['type'] ?? '' );
		$force = ! empty( $_GET['force'] );
		check_admin_referer( 'ccl_generate_document_' . $type );

		$docs = new Documents();
		$s    = Util::settings();
		if ( 'policy' === $type ) {
			$title   = 'Política de tratamiento de datos personales';
			$content = $docs->policy_content();
			$key     = 'policy_page_id';
		} elseif ( 'cookies' === $type ) {
			$title   = 'Preferencias de privacidad y tecnologías';
			$content = $docs->cookie_content() . '[compliance_cl_privacy_center]';
			$key     = 'cookie_page_id';
		} elseif ( 'rights' === $type ) {
			$title   = 'Ejercicio de derechos sobre datos personales';
			$content = '<p>Utiliza este canal para ejercer tus derechos de acceso, rectificación, supresión, oposición, portabilidad o bloqueo.</p>[compliance_cl_rights_form]';
			$key     = 'rights_page_id';
		} else {
			wp_die( esc_html__( 'Documento inválido.', 'wp-compliance-cl' ) );
		}

		$id = absint( $s[ $key ] ?? 0 );
		if ( 'policy' === $type && ! $id && ! $force && $docs->existing_privacy_pages() ) {
			wp_die( esc_html__( 'Ya existe una página relacionada con privacidad. Revísala desde Documentos o confirma explícitamente la creación de un nuevo borrador.', 'wp-compliance-cl' ) );
		}

		$post = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_status'  => 'draft',
			'post_type'    => 'page',
		);
		if ( $id && get_post( $id ) ) {
			$post['ID']          = $id;
			$post['post_status'] = get_post_status( $id ) ?: 'draft';
			$result = wp_update_post( wp_slash( $post ), true );
		} else {
			$result = wp_insert_post( wp_slash( $post ), true );
		}
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		$s[ $key ] = (int) $result;
		update_option( 'ccl_settings', $s, false );
		Audit::log( 'document_generated', 'page', (string) $result, array( 'type' => $type, 'status' => get_post_status( $result ) ) );
		$this->redirect( 'ccl-documents', 'generated' );
	}

	public function download_document(): void { $this->guard(); $type=sanitize_key($_GET['type']??''); $allowed=array('rat','dpa','transfers','breach-plan','dpia'); if(!in_array($type,$allowed,true)){wp_die('Documento inválido');} check_admin_referer('ccl_download_document_'.$type); $content=(new Documents())->markdown($type); Audit::log('document_downloaded','document',$type); nocache_headers(); header('Content-Type: text/markdown; charset=utf-8'); header('Content-Disposition: attachment; filename="compliance-cl-'.$type.'-'.gmdate('Y-m-d').'.md"'); echo $content; exit; }

	public function scanner(): void { $this->guard(); $this->page_start('Escáner de datos y servicios','Detección técnica local. Los hallazgos requieren revisión humana y no constituyen conclusiones jurídicas.'); echo '<div class="ccl-toolbar"><a class="ccl-button ccl-button--primary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ccl_run_scan'),'ccl_run_scan')).'">Ejecutar escaneo</a></div>'; $data=get_transient('ccl_scan_results_'.get_current_user_id()); if(!$data){echo '<section class="ccl-card ccl-empty">Ejecuta el escáner para revisar plugins, tema, usuarios y referencias a servicios externos.</section>';}else{echo '<div class="ccl-scan-grid">'; foreach($data as $f){echo '<section class="ccl-card ccl-finding"><div><span class="ccl-chip ccl-chip--soft">'.esc_html($f['type']).'</span><h3>'.esc_html($f['name']).'</h3><p>'.esc_html($f['detail']).'</p></div><code>'.esc_html($f['evidence']).'</code></section>'; } echo '</div>';} $this->page_end(); }
	public function run_scan(): void { $this->guard(); check_admin_referer('ccl_run_scan'); $data=(new Scanner())->run(); set_transient('ccl_scan_results_'.get_current_user_id(),$data,HOUR_IN_SECONDS); Audit::log('scanner_run','scanner','',array('findings'=>count($data))); $this->redirect('ccl-scanner','scanned'); }

	private function redirect(string $page,string $notice): void { wp_safe_redirect(admin_url('admin.php?page='.$page.'&ccl_notice='.$notice)); exit; }
}
