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
        if ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'asetec-odo' ) return;

        // FullCalendar por CDN
        wp_register_style( 'fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css', [], '6.1.15' );
        wp_register_script('fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true );

        // Nuestro JS con “cache busting”
        $ver = file_exists(ASETEC_ODO_DIR.'assets/js/admin-agenda.js') ? filemtime(ASETEC_ODO_DIR.'assets/js/admin-agenda.js') : ASETEC_Odontologia::VERSION;
        wp_register_script( 'asetec-odo-admin', ASETEC_ODO_URL.'assets/js/admin-agenda.js', [ 'jquery', 'fc-core' ], $ver, true );
    }

    /** Construye el arreglo de eventos (array) para bootstrapping */
    private function build_event( $pid ){
        $s = get_post_meta($pid,'fecha_hora_inicio',true);
        $f = get_post_meta($pid,'fecha_hora_fin',true);

        // ⛔ Validar que existan y tengan formato correcto
        if( empty($s) || empty($f) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$s) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$f) ){
            error_log("ASETEC ODO: Cita ID $pid tiene fechas inválidas. Inicio: $s Fin: $f");
            return null; // se salta esta cita
        }

        $estado = get_post_meta($pid,'estado',true) ?: 'pendiente';
        $pac = get_post_meta($pid,'paciente_nombre',true) ?: 'Sin nombre';

        return [
            'title' => trim($pac.' ['.$estado.']'),
            'start' => str_replace(' ','T',$s),
            'end'   => str_replace(' ','T',$f),
            'extendedProps' => [ 'post_id' => $pid, 'estado'=>$estado ]
        ];
    }


    /** Obtiene eventos del rango visible inicial (semana actual) sin AJAX */
    private function bootstrap_events(){
        try {
            $tz = wp_timezone_string() ?: 'UTC';
            $now = new DateTime('now', new DateTimeZone($tz));
            // lunes de esta semana 00:00
            $dow = (int) $now->format('N'); // 1..7
            $start = (clone $now)->modify('-'.($dow-1).' days')->setTime(0,0,0);
            $end   = (clone $start)->modify('+7 days');

            $q = new WP_Query([
                'post_type'      => 'cita_odontologia',
                'post_status'    => 'any',
                'posts_per_page' => 999,
                'fields'         => 'ids',
                'meta_query'     => [
                    'relation' => 'AND',
                    [ 'key'=>'fecha_hora_inicio', 'compare'=>'<', 'value'=> $end->format('Y-m-d H:i:s'),  'type'=>'DATETIME' ],
                    [ 'key'=>'fecha_hora_fin',    'compare'=>'>', 'value'=> $start->format('Y-m-d H:i:s'),'type'=>'DATETIME' ],
                ],
            ]);
            $events = [];
            foreach( $q->posts as $pid ){
    $event = $this->build_event($pid);
    if($event !== null){
        $events[] = $event;
    }
}

            return $events;
        } catch ( Exception $e ){
            return [];
        }
    }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) return '<p>No autorizado.</p>';

        // Precarga de datos (para arrancar sin AJAX)
        $bootstrap = $this->bootstrap_events();

        wp_enqueue_style('fc-core');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');

        // Pasamos bootstrap y strings al JS
        wp_localize_script( 'asetec-odo-admin', 'ASETEC_ODO_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'bootstrapEvents' => $bootstrap,
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

        ob_start(); ?>
        <style>
          /* Estilos mínimos inline para no depender de otro archivo */
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
