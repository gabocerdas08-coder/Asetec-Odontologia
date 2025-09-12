<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
class ASETEC_ODO_Shortcode_Dashboard {

    const SLUG = 'asetec-odo-dashboard';

    public function __construct(){
        add_shortcode('odo_dashboard', [ $this, 'render' ]);

        // AJAX
        add_action( 'wp_ajax_asetec_odo_dash_kpis',   [ $this, 'ajax_kpis' ] );
        add_action( 'wp_ajax_asetec_odo_dash_series', [ $this, 'ajax_series' ] );
        add_action( 'wp_ajax_asetec_odo_dash_export', [ $this, 'ajax_export' ] );
    }

private function enqueue_assets(){
    // CSS del dashboard
    wp_register_style(
        self::SLUG,
        ASETEC_ODO_URL . 'assets/css/dashboard.css',
        [],
        ASETEC_Odontologia::VERSION
    );

    // Chart.js desde CDN (no requiere archivo local)
    wp_register_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
        [],
        '4.4.3',
        true
    );

    // JS del dashboard (depende de chartjs)
    wp_register_script(
        self::SLUG,
        ASETEC_ODO_URL . 'assets/js/dashboard.js',
        [ 'jquery', 'chartjs' ],
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
            'download'  => __('Descargar CSV', 'asetec-odontologia'),
        ],
        'states' => [
            'pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada'
        ],
    ]);

    wp_enqueue_style( self::SLUG );
    wp_enqueue_script( self::SLUG );
}


    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<div class="asetec-odo-dash wrap"><p>No autorizado.</p></div>';
        }

        $this->enqueue_assets();

        ob_start(); ?>
        <div class="asetec-odo-dash wrap">
            <h2 class="dash-title"><?php esc_html_e('Dashboard Odontología', 'asetec-odontologia'); ?></h2>

            <!-- Filtros -->
            <div class="dash-filters">
                <div class="df-item">
                    <label><?php esc_html_e('Desde', 'asetec-odontologia'); ?></label>
                    <input type="date" id="odo_from" />
                </div>
                <div class="df-item">
                    <label><?php esc_html_e('Hasta', 'asetec-odontologia'); ?></label>
                    <input type="date" id="odo_to" />
                </div>
                <div class="df-item grow">
                    <label><?php esc_html_e('Buscar (nombre o cédula)', 'asetec-odontologia'); ?></label>
                    <input type="text" id="odo_q" placeholder="<?php esc_attr_e('Ej.: 1-2345-6789 o María', 'asetec-odontologia'); ?>" />
                </div>
                <div class="df-item">
                    <label><?php esc_html_e('Estados', 'asetec-odontologia'); ?></label>
                    <div class="df-states" id="odo_states">
                        <label><input type="checkbox" value="pendiente" checked> <?php esc_html_e('Pendiente','asetec-odontologia'); ?></label>
                        <label><input type="checkbox" value="aprobada" checked> <?php esc_html_e('Aprobada','asetec-odontologia'); ?></label>
                        <label><input type="checkbox" value="realizada" checked> <?php esc_html_e('Realizada','asetec-odontologia'); ?></label>
                        <label><input type="checkbox" value="cancelada_usuario" checked> <?php esc_html_e('Cancelada usuario','asetec-odontologia'); ?></label>
                        <label><input type="checkbox" value="cancelada_admin" checked> <?php esc_html_e('Cancelada admin','asetec-odontologia'); ?></label>
                        <label><input type="checkbox" value="reprogramada" checked> <?php esc_html_e('Reprogramada','asetec-odontologia'); ?></label>
                    </div>
                </div>
                <div class="df-item v-end">
                    <button id="odo_refresh" class="button button-primary">
                        <?php esc_html_e('Actualizar', 'asetec-odontologia'); ?>
                    </button>
                    <button id="odo_export" class="button">
                        <?php esc_html_e('Exportar CSV', 'asetec-odontologia'); ?>
                    </button>
                </div>
            </div>

            <!-- KPIs -->
            <div class="dash-kpis">
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Total', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_total">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Pendientes', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_pendiente">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Aprobadas', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_aprobada">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Realizadas', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_realizada">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Canceladas (usuario)', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_cancelada_usuario">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Canceladas (admin)', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_cancelada_admin">—</div></div>
                <div class="kpi"><div class="kpi-label"><?php esc_html_e('Reprogramadas', 'asetec-odontologia'); ?></div><div class="kpi-value" id="kpi_reprogramada">—</div></div>
            </div>

            <!-- Gráficas -->
            <div class="dash-charts">
                <div class="chart-card">
                    <h3><?php esc_html_e('Citas por día (todas las seleccionadas)', 'asetec-odontologia'); ?></h3>
                    <canvas id="chart_total"></canvas>
                </div>
                <div class="chart-card">
                    <h3><?php esc_html_e('Distribución por estado (acumulado)', 'asetec-odontologia'); ?></h3>
                    <canvas id="chart_states"></canvas>
                </div>
            </div>

            <div class="dash-log" id="dash_log" aria-live="polite"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** -------- Helpers comunes -------- */

    private function parse_filters(){
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : '';
        $q    = isset($_POST['q'])    ? sanitize_text_field($_POST['q'])    : '';
        $states = [];
        if ( ! empty($_POST['states']) && is_array($_POST['states']) ) {
            $states = array_map('sanitize_text_field', $_POST['states']);
        }

        $from_ts = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) ? strtotime($from.' 00:00:00') : 0;
        $to_ts   = $to   && preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)   ? strtotime($to.' 23:59:59') : 0;

        $meta_query = [];
        if ( $from_ts || $to_ts ) {
            $val_from = $from_ts ? date('Y-m-d H:i:s', $from_ts) : '0000-01-01 00:00:00';
            $val_to   = $to_ts   ? date('Y-m-d H:i:s', $to_ts)   : '9999-12-31 23:59:59';
            $meta_query[] = [
                'key'     => 'fecha_hora_inicio',
                'value'   => [ $val_from, $val_to ],
                'type'    => 'DATETIME',
                'compare' => 'BETWEEN',
            ];
        }

        // search por nombre/cédula correo/teléfono
        $s_meta = [];
        if ( $q ) {
            $s_meta = [
                'relation' => 'OR',
                [
                    'key'     => 'paciente_nombre',
                    'value'   => $q,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'paciente_cedula',
                    'value'   => $q,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'paciente_correo',
                    'value'   => $q,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'paciente_telefono',
                    'value'   => $q,
                    'compare' => 'LIKE',
                ],
            ];
        }
        if ( $s_meta ) {
            $meta_query[] = $s_meta;
        }

        return [ $meta_query, $states ];
    }

    /** -------- AJAX: KPIs -------- */
    public function ajax_kpis(){
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'No autorizado'],403);
        check_ajax_referer( 'asetec_odo_dash', 'nonce' );

        [ $meta_query, $states ] = $this->parse_filters();

        $args = [
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ];

        $ids = get_posts($args);

        $kpis = [
            'total' => 0,
            'pendiente'=>0,'aprobada'=>0,'realizada'=>0,
            'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0,
        ];

        if ( $ids ) {
            foreach ( $ids as $pid ) {
                $estado = get_post_meta( $pid, 'estado', true );
                if ( ! $states || in_array($estado, $states, true) ) {
                    $kpis['total']++;
                    if ( isset($kpis[$estado]) ) $kpis[$estado]++;
                }
            }
        }

        wp_send_json_success([ 'kpis'=>$kpis ]);
    }

    /** -------- AJAX: Series para gráficas -------- */
    public function ajax_series(){
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'No autorizado'],403);
        check_ajax_referer( 'asetec_odo_dash', 'nonce' );

        [ $meta_query, $states ] = $this->parse_filters();

        $args = [
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ];
        $ids = get_posts($args);

        $by_date = [];    // 'Y-m-d' => total
        $by_state = [ 'pendiente'=>0,'aprobada'=>0,'realizada'=>0,'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0 ];

        if ( $ids ) {
            foreach ( $ids as $pid ) {
                $estado = get_post_meta( $pid, 'estado', true );
                $start  = get_post_meta( $pid, 'fecha_hora_inicio', true );
                $day    = $start ? substr($start,0,10) : 'sin_fecha';

                if ( ! $states || in_array($estado, $states, true) ) {
                    $by_date[$day] = ($by_date[$day] ?? 0) + 1;
                    if ( isset($by_state[$estado]) ) $by_state[$estado]++;
                }
            }
        }

        ksort($by_date);
        $labels = array_keys($by_date);
        $values = array_values($by_date);

        wp_send_json_success([
            'labels' => $labels,
            'total'  => $values,
            'states' => $by_state,
        ]);
    }

    /** -------- AJAX: Export CSV -------- */
    public function ajax_export(){
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'No autorizado'],403);
        check_ajax_referer( 'asetec_odo_dash', 'nonce' );

        [ $meta_query, $states ] = $this->parse_filters();

        $args = [
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => $meta_query,
        ];

        $q = new WP_Query($args);
        $rows = [];
        $rows[] = ['ID','Estado','Inicio','Fin','Nombre','Cédula','Correo','Teléfono'];

        if ( $q->have_posts() ) {
            foreach ( $q->posts as $p ) {
                $pid = $p->ID;
                $estado = get_post_meta($pid,'estado',true);
                if ( $states && !in_array($estado,$states,true) ) continue;

                $rows[] = [
                    $pid,
                    $estado,
                    get_post_meta($pid,'fecha_hora_inicio',true),
                    get_post_meta($pid,'fecha_hora_fin',true),
                    get_post_meta($pid,'paciente_nombre',true),
                    get_post_meta($pid,'paciente_cedula',true),
                    get_post_meta($pid,'paciente_correo',true),
                    get_post_meta($pid,'paciente_telefono',true),
                ];
            }
        }

        // Devolvemos CSV como string (el front hace descarga)
        $fh = fopen('php://temp', 'w');
        foreach($rows as $r){ fputcsv($fh, $r); }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        wp_send_json_success([ 'csv' => $csv ]);
    }
}
}
