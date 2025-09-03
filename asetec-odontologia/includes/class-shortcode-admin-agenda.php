<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
        // Cargar assets tanto en admin como en el front (usás Elementor)
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
    }

    public function assets( $hook = '' ){
        // Evitar la página interna de ajustes del plugin
        if ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'asetec-odo' ) return;

        // FullCalendar (CDN para simplificar)
        wp_register_style( 'fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css', [], '6.1.15' );
        wp_register_script('fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true );

        // CSS/JS propios
        wp_register_style( 'asetec-odo-admin-css', ASETEC_ODO_URL.'assets/css/admin-agenda.css', [], ASETEC_Odontologia::VERSION );
        $ver = file_exists(ASETEC_ODO_DIR.'assets/js/admin-agenda.js') ? filemtime(ASETEC_ODO_DIR.'assets/js/admin-agenda.js') : ASETEC_Odontologia::VERSION;
        wp_register_script( 'asetec-odo-admin', ASETEC_ODO_URL.'assets/js/admin-agenda.js', [ 'jquery', 'fc-core' ], $ver, true );

        wp_localize_script( 'asetec-odo-admin', 'ASETEC_ODO_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'i18n'  => [
                'create_title' => __('Nueva cita', 'asetec-odontologia'),
                'edit_title'   => __('Cita', 'asetec-odontologia'),
                'approve'      => __('Aprobar', 'asetec-odontologia'),
                'cancel'       => __('Cancelar', 'asetec-odontologia'),
                'done'         => __('Realizada', 'asetec-odontologia'),
                'save'         => __('Guardar', 'asetec-odontologia'),
                'update'       => __('Actualizar', 'asetec-odontologia'),
                'close'        => __('Cerrar', 'asetec-odontologia'),
            ]
        ] );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) return '<p>No autorizado.</p>';

        wp_enqueue_style('fc-core');
        wp_enqueue_style('asetec-odo-admin-css');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');

        ob_start(); ?>
        <div class="wrap modulo-asetec">
            <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>
            <div id="odo-calendar" class="odo-calendar"></div>
        </div>

        <!-- Modal ASETEC (crear/gestionar) -->
        <div id="odo-modal" class="odo-modal" aria-hidden="true">
          <div class="odo-modal__backdrop"></div>
          <div class="odo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="odo-modal-title">
            <div class="odo-modal__header">
              <h3 id="odo-modal-title"></h3>
              <button type="button" class="odo-btn odo-btn--ghost" id="odo-btn-close">✕</button>
            </div>

            <div class="odo-modal__body">
              <form id="odo-form">
                <input type="hidden" name="post_id" id="odo_post_id">
                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Inicio</label>
                    <input type="datetime-local" id="odo_start" name="start" required>
                  </div>
                  <div class="odo-field">
                    <label>Fin</label>
                    <input type="datetime-local" id="odo_end" name="end" required>
                  </div>
                </div>

                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Nombre completo</label>
                    <input type="text" id="odo_nombre" name="nombre" required>
                  </div>
                  <div class="odo-field">
                    <label>Cédula</label>
                    <input type="text" id="odo_cedula" name="cedula" required>
                  </div>
                </div>

                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Correo</label>
                    <input type="email" id="odo_correo" name="correo" required>
                  </div>
                  <div class="odo-field">
                    <label>Teléfono</label>
                    <input type="tel" id="odo_telefono" name="telefono" required>
                  </div>
                </div>

                <div class="odo-field">
                  <label>Estado</label>
                  <input type="text" id="odo_estado" name="estado" readonly>
                </div>
              </form>
            </div>

            <div class="odo-modal__footer">
              <div class="odo-actions-left">
                <button type="button" class="odo-btn" id="odo-btn-save"><?php esc_html_e('Guardar', 'asetec-odontologia'); ?></button>
                <button type="button" class="odo-btn" id="odo-btn-update" style="display:none;"><?php esc_html_e('Actualizar', 'asetec-odontologia'); ?></button>
              </div>
              <div class="odo-actions-right">
                <button type="button" class="odo-btn odo-btn--primary" id="odo-btn-approve"><?php esc_html_e('Aprobar', 'asetec-odontologia'); ?></button>
                <button type="button" class="odo-btn odo-btn--warn"    id="odo-btn-done"><?php esc_html_e('Realizada', 'asetec-odontologia'); ?></button>
                <button type="button" class="odo-btn odo-btn--danger"  id="odo-btn-cancel"><?php esc_html_e('Cancelar', 'asetec-odontologia'); ?></button>
              </div>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

} // class_exists
