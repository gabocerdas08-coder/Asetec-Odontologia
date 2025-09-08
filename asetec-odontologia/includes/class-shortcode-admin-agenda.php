<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets( $hook = '' ){
        // FullCalendar (https)
        wp_register_style(
            'fc-core',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css',
            [],
            '6.1.15'
        );
        wp_register_script(
            'fc-core',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js',
            [],
            '6.1.15',
            true
        );

        // Nuestro JS
        wp_register_script(
            'asetec-odo-admin',
            ASETEC_ODO_URL . 'assets/js/admin-agenda.js',
            [ 'jquery', 'fc-core' ],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script( 'asetec-odo-admin', 'ASETEC_ODO_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
        ] );
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) return '<p>No autorizado.</p>';

        wp_enqueue_style('fc-core');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');

        ob_start(); ?>
        <style>
          /* Controles */
          .odo-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin:8px 0 14px; }
          .odo-legend { display:flex; gap:12px; flex-wrap:wrap; font-size:12px; color:#374151; }
          .odo-legend .dot { width:10px; height:10px; border-radius:999px; display:inline-block; margin-right:6px; vertical-align:middle; }
          .odo-filters { display:flex; gap:8px; flex-wrap:wrap; font-size:12px; }
          .odo-filters label { display:inline-flex; gap:6px; align-items:center; background:#f3f4f6; padding:6px 8px; border-radius:10px; border:1px solid #e5e7eb; cursor:pointer; }
          .odo-search { min-width:260px; border:1px solid #d1d5db; border-radius:10px; padding:8px 10px; }
          .odo-btn-primary { background:#d97706; color:#fff; border:1px solid #d97706; border-radius:10px; padding:8px 12px; cursor:pointer; }
          .odo-btn-primary:hover { background:#b45309; }

          /* Calendar */
          .odo-calendar .fc { --fc-border-color:#e5e7eb; font-family: Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; }
          .odo-calendar .fc-timegrid-slot-label { font-size:12px; color:#374151; }
          .odo-calendar .fc-toolbar-title { font-weight:700; letter-spacing:0.2px; }
          .odo-calendar .fc-button { border-radius:10px; padding:6px 10px; }
          .odo-calendar .fc-event { border-radius:10px; padding:0 6px; font-size:12px; }
          .odo-calendar .fc-timegrid-slot { height:1.6em; }
          .odo-calendar { min-height:640px; }

          /* Chips */
          .odo-chip { display:inline-block; padding:1px 6px; border-radius:999px; font-size:10px; line-height:1.6; color:#fff; margin-right:6px; }
          .chip-pendiente{background:#f59e0b} .chip-aprobada{background:#3b82f6} .chip-realizada{background:#10b981}
          .chip-cancelada_usuario,.chip-cancelada_admin{background:#ef4444} .chip-reprogramada{background:#8b5cf6}

          /* Modal robusto (va en <body>) */
          .odo-modal { position:fixed; inset:0; z-index:100000; display:none; }
          .odo-modal.is-open { display:block; }
          .odo-modal__backdrop { position:absolute; inset:0; background:rgba(15,23,42,.45); }
          .odo-modal__dialog { position:relative; max-width:760px; margin:6vh auto; background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; }
          .odo-modal__header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e5e7eb; }
          .odo-modal__body { padding:16px 18px; }
          .odo-modal__footer { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 18px; border-top:1px solid #e5e7eb; background:#fafafa; }
          .odo-actions-left,.odo-actions-right { display:flex; gap:8px; flex-wrap:wrap; }
          .odo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; } @media (max-width:720px){ .odo-grid{ grid-template-columns:1fr; } }
          .odo-field label { display:block; font-size:12px; color:#374151; margin:0 0 4px; }
          .odo-field input[type="text"],.odo-field input[type="email"],.odo-field input[type="tel"],.odo-field input[type="datetime-local"], .odo-field select, .odo-field textarea{ width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; }
          .odo-field textarea{ min-height:80px; resize:vertical; }
          .odo-btn { appearance:none; border:1px solid #d1d5db; background:#fff; color:#111827; padding:8px 12px; border-radius:10px; cursor:pointer; }
          .odo-btn:hover { background:#f3f4f6; }
          .odo-btn--primary { background:#1d4ed8; color:#fff; border-color:#1d4ed8; } .odo-btn--primary:hover { background:#1e40af; }
          .odo-btn--danger  { background:#b91c1c; color:#fff; border-color:#b91c1c; } .odo-btn--danger:hover  { background:#991b1b; }
          .odo-btn--warn    { background:#059669; color:#fff; border-color:#059669; } .odo-btn--warn:hover    { background:#047857; }

          .odo-toast { position:fixed; right:14px; bottom:14px; background:#111827; color:#fff; padding:10px 12px; border-radius:10px; font-size:12px; box-shadow:0 10px 30px rgba(0,0,0,.25); opacity:0; transform:translateY(10px); transition:all .2s ease; z-index:100001; }
          .odo-toast.show{ opacity:1; transform:translateY(0); }
        </style>

        <div class="wrap modulo-asetec">
          <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>

          <div class="odo-toolbar">
            <div class="odo-legend">
              <span><i class="dot" style="background:#f59e0b"></i> Pendiente</span>
              <span><i class="dot" style="background:#3b82f6"></i> Aprobada</span>
              <span><i class="dot" style="background:#10b981"></i> Realizada</span>
              <span><i class="dot" style="background:#ef4444"></i> Cancelada</span>
              <span><i class="dot" style="background:#8b5cf6"></i> Reprogramada</span>
            </div>
            <input id="odo-search" class="odo-search" type="search" placeholder="<?php echo esc_attr__('Buscar por nombre o cédula…','asetec-odontologia'); ?>" />
            <button id="odo-btn-new" class="odo-btn-primary"><?php esc_html_e('Nueva cita','asetec-odontologia'); ?></button>
          </div>

          <div class="odo-filters" id="odo-filters">
            <?php
              $estados = ['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada'];
              foreach($estados as $e){
                $label = ucwords(str_replace('_',' ', $e));
                echo '<label><input type="checkbox" class="odo-filter" value="'.esc_attr($e).'" checked> '.esc_html($label).'</label>';
              }
            ?>
          </div>

          <div id="odo-calendar" class="odo-calendar"></div>
        </div>

        <!-- Modal (se moverá a <body> con JS para evitar conflictos del tema) -->
        <div id="odo-modal" class="odo-modal" aria-hidden="true">
          <div class="odo-modal__backdrop"></div>
          <div class="odo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="odo-modal-title">
            <div class="odo-modal__header">
              <h3 id="odo-modal-title"></h3>
              <button type="button" class="odo-btn" id="odo-btn-close">✕</button>
            </div>
            <div class="odo-modal__body">
              <form id="odo-form">
                <input type="hidden" id="odo_post_id">
                <div class="odo-grid">
                  <div class="odo-field">
                    <label><?php esc_html_e('Inicio','asetec-odontologia'); ?></label>
                    <input type="datetime-local" id="odo_start" step="600" required>
                  </div>
                  <div class="odo-field">
                    <label><?php esc_html_e('Fin','asetec-odontologia'); ?></label>
                    <input type="datetime-local" id="odo_end" step="600" required>
                  </div>
                </div>

                <div class="odo-grid">
                  <div class="odo-field">
                    <label><?php esc_html_e('Duración (min)','asetec-odontologia'); ?></label>
                    <select id="odo_duration">
                      <option value="20">20</option>
                      <option value="30">30</option>
                      <option value="40" selected>40</option>
                      <option value="60">60</option>
                      <option value="custom">Custom…</option>
                    </select>
                  </div>
                  <div class="odo-field" id="odo_custom_wrap" style="display:none;">
                    <label><?php esc_html_e('Duración personalizada (min)','asetec-odontologia'); ?></label>
                    <input type="number" id="odo_custom_minutes" min="10" step="5" placeholder="45">
                  </div>
                </div>

                <div class="odo-grid">
                  <div class="odo-field">
                    <label><?php esc_html_e('Nombre completo','asetec-odontologia'); ?></label>
                    <input type="text" id="odo_nombre" required>
                  </div>
                  <div class="odo-field">
                    <label><?php esc_html_e('Cédula','asetec-odontologia'); ?></label>
                    <input type="text" id="odo_cedula" required>
                  </div>
                </div>

                <div class="odo-grid">
                  <div class="odo-field">
                    <label><?php esc_html_e('Correo','asetec-odontologia'); ?></label>
                    <input type="email" id="odo_correo" required>
                  </div>
                  <div class="odo-field">
                    <label><?php esc_html_e('Teléfono','asetec-odontologia'); ?></label>
                    <input type="tel" id="odo_telefono" required>
                  </div>
                </div>

                <div class="odo-field">
                  <label><?php esc_html_e('Estado','asetec-odontologia'); ?></label>
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

        <div id="odo-toast" class="odo-toast" role="status" aria-live="polite"></div>
        <?php
        return ob_get_clean();
    }
}

}
