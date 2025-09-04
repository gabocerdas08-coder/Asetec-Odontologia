<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
    }

    public function assets( $hook = '' ){
        // Evita la página de ajustes interna
        if ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'asetec-odo' ) return;

        // FullCalendar por CDN (sin dependencias locales)
        wp_register_style( 'fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css', [], '6.1.15' );
        wp_register_script('fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true );

        // Nuestro JS (versionado con time() para romper caché)
        wp_register_script( 'asetec-odo-admin', ASETEC_ODO_URL.'assets/js/admin-agenda.js', [ 'jquery', 'fc-core' ], time(), true );

        wp_localize_script( 'asetec-odo-admin', 'ASETEC_ODO_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'bootstrapEvents' => [], // SAFE: no pre-carga; evitamos WP_Query aquí
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
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');

        ob_start(); ?>
        <style>
          /* Estilos inline, sin archivo CSS externo */
          .odo-calendar .fc { --fc-border-color:#e5e7eb; font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; }
          .odo-calendar .fc-timegrid-slot-label { font-size:12px; color:#374151; }
          .odo-calendar .fc-toolbar-title { font-weight:700; letter-spacing:0.2px; }
          .odo-calendar .fc-event { border-radius:8px; padding:2px 4px; font-size:12px; }
          .odo-calendar .fc-timegrid-slot { height:1.6em; }
          .odo-calendar { min-height:640px; }
          .odo-modal { position:fixed; inset:0; z-index:9999; display:none; }
          .odo-modal.is-open { display:block; }
          .odo-modal__backdrop { position:absolute; inset:0; background:rgba(15,23,42,.45); }
          .odo-modal__dialog { position:relative; max-width:720px; margin:6vh auto; background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; }
          .odo-modal__header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e5e7eb; }
          .odo-modal__body { padding:16px 18px; }
          .odo-modal__footer { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 18px; border-top:1px solid #e5e7eb; background:#fafafa; }
          .odo-actions-left,.odo-actions-right { display:flex; gap:8px; flex-wrap:wrap; }
          .odo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; } @media (max-width:680px){ .odo-grid{ grid-template-columns:1fr; } }
          .odo-field label { display:block; font-size:12px; color:#374151; margin:0 0 4px; }
          .odo-field input[type="text"],.odo-field input[type="email"],.odo-field input[type="tel"],.odo-field input[type="datetime-local"]{ width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; }
          .odo-btn { appearance:none; border:1px solid #d1d5db; background:#fff; color:#111827; padding:8px 12px; border-radius:10px; cursor:pointer; }
          .odo-btn:hover { background:#f3f4f6; }
          .odo-btn--primary { background:#1d4ed8; color:#fff; border-color:#1d4ed8; } .odo-btn--primary:hover { background:#1e40af; }
          .odo-btn--danger { background:#b91c1c; color:#fff; border-color:#b91c1c; } .odo-btn--danger:hover { background:#991b1b; }
          .odo-btn--warn { background:#059669; color:#fff; border-color:#059669; } .odo-btn--warn:hover { background:#047857; }
          .odo-btn--ghost { background:transparent; border:none; font-size:18px; color:#6b7280; } .odo-btn--ghost:hover { color:#111827; }
        </style>

        <div class="wrap modulo-asetec">
            <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>
            <div id="odo-calendar" class="odo-calendar"></div>
        </div>

        <!-- Modal ASETEC -->
        <div id="odo-modal" class="odo-modal" aria-hidden="true">
          <div class="odo-modal__backdrop"></div>
          <div class="odo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="odo-modal-title">
            <div class="odo-modal__header">
              <h3 id="odo-modal-title"></h3>
              <button type="button" class="odo-btn odo-btn--ghost" id="odo-btn-close">✕</button>
            </div>
            <div class="odo-modal__body">
              <form id="odo-form">
                <input type="hidden" id="odo_post_id">
                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Inicio</label>
                    <input type="datetime-local" id="odo_start" required>
                  </div>
                  <div class="odo-field">
                    <label>Fin</label>
                    <input type="datetime-local" id="odo_end" required>
                  </div>
                </div>
                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Nombre completo</label>
                    <input type="text" id="odo_nombre" required>
                  </div>
                  <div class="odo-field">
                    <label>Cédula</label>
                    <input type="text" id="odo_cedula" required>
                  </div>
                </div>
                <div class="odo-grid">
                  <div class="odo-field">
                    <label>Correo</label>
                    <input type="email" id="odo_correo" required>
                  </div>
                  <div class="odo-field">
                    <label>Teléfono</label>
                    <input type="tel" id="odo_telefono" required>
                  </div>
                </div>
                <div class="odo-field">
                  <label>Estado</label>
                  <input type="text" id="odo_estado" readonly>
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
