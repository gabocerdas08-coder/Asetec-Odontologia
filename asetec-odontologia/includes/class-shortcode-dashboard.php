<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
class ASETEC_ODO_Shortcode_Dashboard {

    const SLUG = 'asetec-odo-dashboard';

    public function __construct(){
        add_shortcode('odo_dashboard', [ $this, 'render' ]);

        // AJAX (solo usuarios logueados con permiso)
        add_action( 'wp_ajax_asetec_odo_dash_kpis', [ $this, 'ajax_kpis' ] );
    }

    /** Encola assets SOLO cuando se usa el shortcode */
    private function enqueue_assets(){
        // CSS
        wp_register_style(
            self::SLUG,
            ASETEC_ODO_URL . 'assets/css/dashboard.css',
            [],
            ASETEC_Odontologia::VERSION
        );

        // JS
        wp_register_script(
            self::SLUG,
            ASETEC_ODO_URL . 'assets/js/dashboard.js',
            [ 'jquery' ],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script( self::SLUG, 'ASETEC_ODO_DASH', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_dash'),
            'i18n'  => [
                'loading'   => __('Cargando…', 'asetec-odontologia'),
                'error'     => __('Ocurrió un error al cargar.', 'asetec-odontologia'),
                'na'        => __('N/D', 'asetec-odontologia'),
            ],
        ]);

        wp_enqueue_style( self::SLUG );
        wp_enqueue_script( self::SLUG );
    }

    public function render(){
        // Restringe a admin / recep / doctor (ajusta si querés otra capability)
        if ( ! current_user_can('manage_options') ) {
            return '<div class="asetec-odo-dash wrap"><p>No autorizado.</p></div>';
        }

        $this->enqueue_assets();

        // UI mínima para depurar que JS/CSS/ AJAX funcionan
        ob_start(); ?>
        <div class="asetec-odo-dash wrap">
            <h2 class="dash-title"><?php esc_html_e('Dashboard Odontología', 'asetec-odontologia'); ?></h2>

            <div class="dash-filters">
                <div class="df-item">
                    <label><?php esc_html_e('Desde', 'asetec-odontologia'); ?></label>
                    <input type="date" id="odo_from" />
                </div>
                <div class="df-item">
                    <label><?php esc_html_e('Hasta', 'asetec-odontologia'); ?></label>
                    <input type="date" id="odo_to" />
                </div>
                <div class="df-item">
                    <button id="odo_refresh" class="button button-primary">
                        <?php esc_html_e('Actualizar', 'asetec-odontologia'); ?>
                    </button>
                </div>
            </div>

            <div class="dash-kpis">
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Total', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_total">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Pendientes', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_pendiente">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Aprobadas', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_aprobada">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Realizadas', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_realizada">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Canceladas (usuario)', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_cancelada_usuario">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Canceladas (admin)', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_cancelada_admin">—</div>
                </div>
                <div class="kpi">
                    <div class="kpi-label"><?php esc_html_e('Reprogramadas', 'asetec-odontologia'); ?></div>
                    <div class="kpi-value" id="kpi_reprogramada">—</div>
                </div>
            </div>

            <div class="dash-log" id="dash_log" aria-live="polite"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** AJAX: KPIs por rango de fechas (fecha_hora_inicio) */
    public function ajax_kpis(){
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['msg'=>'No autorizado'], 403);
        }
        check_ajax_referer( 'asetec_odo_dash', 'nonce' );

        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : '';

        // Validación simple de fechas
        $from_ts = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) ? strtotime($from.' 00:00:00') : 0;
        $to_ts   = $to   && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)   ? strtotime($to.' 23:59:59') : 0;

        // Construye meta_query por rango
        $meta_query = [];
        if ( $from_ts || $to_ts ) {
            $range = [ 'key' => 'fecha_hora_inicio', 'compare' => 'EXISTS' ];
            // Usamos BETWEEN (ISO Y-m-d H:i:s guardado en meta)
            $val_from = $from_ts ? date('Y-m-d H:i:s', $from_ts) : '0000-01-01 00:00:00';
            $val_to   = $to_ts   ? date('Y-m-d H:i:s', $to_ts)   : '9999-12-31 23:59:59';
            $meta_query[] = [
                'key'     => 'fecha_hora_inicio',
                'value'   => [ $val_from, $val_to ],
                'type'    => 'DATETIME',
                'compare' => 'BETWEEN',
            ];
        }

        // Consulta general para traer IDs (para luego contar por estado sin hacer mil queries)
        $args = [
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ];

        $ids = get_posts( $args );

        $kpis = [
            'total'              => 0,
            'pendiente'          => 0,
            'aprobada'           => 0,
            'realizada'          => 0,
            'cancelada_usuario'  => 0,
            'cancelada_admin'    => 0,
            'reprogramada'       => 0,
        ];

        if ( $ids ) {
            $kpis['total'] = count($ids);
            foreach ( $ids as $pid ) {
                $estado = get_post_meta( $pid, 'estado', true );
                if ( isset($kpis[$estado]) ) {
                    $kpis[$estado] ++;
                }
            }
        }

        wp_send_json_success( [ 'kpis' => $kpis ] );
    }
}
}
