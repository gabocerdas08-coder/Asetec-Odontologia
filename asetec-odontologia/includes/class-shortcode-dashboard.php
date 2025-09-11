<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Shortcode_Dashboard {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_dashboard', [ $this, 'render' ] );

        // AJAX (solo admin-ajax, no carga frontend)
        add_action( 'wp_ajax_asetec_odo_dash_summary', [ $this, 'ajax_summary' ] );
        add_action( 'wp_ajax_asetec_odo_dash_series',  [ $this, 'ajax_series' ] );
        add_action( 'wp_ajax_asetec_odo_dash_export',  [ $this, 'ajax_export' ] );
    }

    private function can_view(){
        return current_user_can('manage_options') || current_user_can('manage_odontologia');
    }

    private function ver(){
        return class_exists('ASETEC_Odontologia') ? ASETEC_Odontologia::VERSION : '0.0.0';
    }

    private function enqueue_assets(){
        // CSS del dashboard
        if ( defined('ASETEC_ODO_URL') ) {
            wp_enqueue_style(
                'asetec-odo-dashboard',
                ASETEC_ODO_URL . 'assets/css/dashboard.css',
                [],
                $this->ver()
            );
        }

        // Chart.js: usa local si existe, si no CDN
        $local_chart = ( defined('ASETEC_ODO_DIR') && file_exists( ASETEC_ODO_DIR.'assets/chartjs/chart.umd.js' ) )
            ? ASETEC_ODO_URL . 'assets/chartjs/chart.umd.js'
            : 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';

        wp_enqueue_script( 'chartjs', $local_chart, [], '4.4.0', true );

        // JS del dashboard
        if ( defined('ASETEC_ODO_URL') ) {
            wp_enqueue_script(
                'asetec-odo-dashboard',
                ASETEC_ODO_URL . 'assets/js/dashboard.js',
                [ 'jquery', 'chartjs' ],
                $this->ver(),
                true
            );
            wp_localize_script( 'asetec-odo-dashboard', 'ASETEC_ODO_DASH', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('asetec_odo_dash'),
                'i18n'  => [
                    'loading'  => __( 'Cargando…', 'asetec-odontologia' ),
                    'nodata'   => __( 'Sin datos en el rango elegido', 'asetec-odontologia' ),
                    'export'   => __( 'Exportar CSV', 'asetec-odontologia' ),
                ]
            ] );
        }
    }

    public function render(){
        if ( ! $this->can_view() ) {
            return '<div class="asetec-odo-alert error">'.esc_html__('No tiene permisos para ver este tablero.', 'asetec-odontologia').'</div>';
        }

        // Asegura que las constantes existan para rutas (si tu bootstrap aún no corrió)
        if ( ! defined('ASETEC_ODO_URL') || ! defined('ASETEC_ODO_DIR') ) {
            // evita fatales si alguien carga el shortcode fuera del contexto del plugin
            return '<div class="asetec-odo-alert error">ASETEC Odontología: rutas no inicializadas.</div>';
        }

        $this->enqueue_assets();

        ob_start(); ?>
        <div class="odo-dash">
            <div class="odo-dash__filters">
                <div class="odo-dash__range">
                    <label><?php esc_html_e('Rango', 'asetec-odontologia'); ?></label>
                    <div class="quick">
                        <button data-range="7"><?php esc_html_e('7 días', 'asetec-odontologia'); ?></button>
                        <button data-range="30" class="active"><?php esc_html_e('30 días', 'asetec-odontologia'); ?></button>
                        <button data-range="90"><?php esc_html_e('90 días', 'asetec-odontologia'); ?></button>
                        <button data-range="365"><?php esc_html_e('1 año', 'asetec-odontologia'); ?></button>
                    </div>
                    <div class="custom">
                        <input type="date" id="odo_from">
                        <span>—</span>
                        <input type="date" id="odo_to">
                        <button id="odo_apply"><?php esc_html_e('Aplicar', 'asetec-odontologia'); ?></button>
                    </div>
                </div>

                <div class="odo-dash__states">
                    <label><?php esc_html_e('Estados', 'asetec-odontologia'); ?></label>
                    <div class="chips">
                        <?php
                        $states = [
                            'pendiente'         => __('Pendiente','asetec-odontologia'),
                            'aprobada'          => __('Aprobada','asetec-odontologia'),
                            'realizada'         => __('Realizada','asetec-odontologia'),
                            'cancelada_usuario' => __('Cancelada Usuario','asetec-odontologia'),
                            'cancelada_admin'   => __('Cancelada Admin','asetec-odontologia'),
                            'reprogramada'      => __('Reprogramada','asetec-odontologia'),
                        ];
                        foreach($states as $k=>$label){
                            echo '<label><input type="checkbox" class="st" value="'.esc_attr($k).'" checked> '.esc_html($label).'</label>';
                        }
                        ?>
                    </div>
                </div>

                <div class="odo-dash__actions">
                    <button id="odo_export"><?php esc_html_e('Exportar CSV','asetec-odontologia'); ?></button>
                </div>
            </div>

            <div class="odo-dash__kpis">
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Citas','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_total">—</div></div>
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Aprobadas','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_aprobadas">—</div></div>
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Realizadas','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_realizadas">—</div></div>
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Canceladas','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_canceladas">—</div></div>
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Reprogramadas','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_reprogramadas">—</div></div>
                <div class="kpi"><div class="kpi__label"><?php esc_html_e('Prom. día','asetec-odontologia'); ?></div><div class="kpi__value" id="kpi_promdia">—</div></div>
            </div>

            <div class="odo-dash__grid">
                <div class="panel">
                    <div class="panel__title"><?php esc_html_e('Citas por día (Total / Estados)','asetec-odontologia'); ?></div>
                    <canvas id="ch_series" height="110"></canvas>
                </div>

                <div class="panel">
                    <div class="panel__title"><?php esc_html_e('Distribución por estado','asetec-odontologia'); ?></div>
                    <canvas id="ch_pie" height="110"></canvas>
                </div>

                <div class="panel">
                    <div class="panel__title"><?php esc_html_e('Citas por hora del día','asetec-odontologia'); ?></div>
                    <canvas id="ch_hours" height="110"></canvas>
                </div>
            </div>

            <div class="panel">
                <div class="panel__title"><?php esc_html_e('Resumen (top días / horas)','asetec-odontologia'); ?></div>
                <div class="odo-dash__summary" id="summary_table"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ----------------- DATA (AJAX) ----------------- */

    private function guard(){
        if ( ! $this->can_view() ) wp_send_json_error(['msg'=>'forbidden'], 403);
        check_ajax_referer( 'asetec_odo_dash', 'nonce' );
        global $wpdb;
        return $wpdb;
    }

    private function parse_range(){
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : '';
        $to   = isset($_POST['to'])   ? sanitize_text_field($_POST['to'])   : '';
        $range = max(1, intval($_POST['range'] ?? 30));
        if ( !preg_match('/^\d{4}-\d{2}-\d{2}$/',$from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$to) ){
            $to = current_time('Y-m-d');
            $from = date('Y-m-d', strtotime($to.' -'.$range.' days'));
        }
        return [$from.' 00:00:00', $to.' 23:59:59', $from, $to];
    }
    private function parse_states(){
        $allowed = ['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada'];
        $in = isset($_POST['states']) && is_array($_POST['states']) ? array_map('sanitize_text_field', $_POST['states']) : $allowed;
        $in = array_values(array_intersect($in, $allowed));
        if (empty($in)) $in = $allowed;
        return $in;
    }

    public function ajax_summary(){
        $wpdb = $this->guard();
        list($from_dt,$to_dt,$from,$to) = $this->parse_range();
        $states = $this->parse_states();
        $ph = implode(',', array_fill(0, count($states), '%s'));
        $args = array_merge([$from_dt,$to_dt], $states);

        $sql = "
        SELECT est.meta_value estado, COUNT(p.ID) n
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} s ON (s.post_id=p.ID AND s.meta_key='fecha_hora_inicio')
        INNER JOIN {$wpdb->postmeta} est ON (est.post_id=p.ID AND est.meta_key='estado')
        WHERE p.post_type='cita_odontologia' AND p.post_status='publish'
          AND s.meta_value BETWEEN %s AND %s
          AND est.meta_value IN ($ph)
        GROUP BY est.meta_value";
        $rows = $wpdb->get_results( $wpdb->prepare($sql,$args), ARRAY_A );

        $by_estado = array_fill_keys($states, 0); $total=0;
        foreach($rows as $r){ $by_estado[$r['estado']] = intval($r['n']); $total += intval($r['n']); }

        $days = max(1, (int) ceil((strtotime($to_dt)-strtotime($from_dt))/86400));
        $prom_dia = $total / $days;

        $sqlH = "
        SELECT DATE_FORMAT(s.meta_value,'%%H') hh, COUNT(*) n
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} s ON (s.post_id=p.ID AND s.meta_key='fecha_hora_inicio')
        INNER JOIN {$wpdb->postmeta} est ON (est.post_id=p.ID AND est.meta_key='estado')
        WHERE p.post_type='cita_odontologia' AND p.post_status='publish'
          AND s.meta_value BETWEEN %s AND %s
          AND est.meta_value IN ($ph)
        GROUP BY hh ORDER BY hh";
        $rowsH = $wpdb->get_results( $wpdb->prepare($sqlH,$args), ARRAY_A );
        $hours = []; foreach($rowsH as $r){ $hours[$r['hh']] = intval($r['n']); }

        $sqlD = "
        SELECT DATE(s.meta_value) d, COUNT(*) n
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} s ON (s.post_id=p.ID AND s.meta_key='fecha_hora_inicio')
        INNER JOIN {$wpdb->postmeta} est ON (est.post_id=p.ID AND est.meta_key='estado')
        WHERE p.post_type='cita_odontologia' AND p.post_status='publish'
          AND s.meta_value BETWEEN %s AND %s
          AND est.meta_value IN ($ph)
        GROUP BY d ORDER BY n DESC LIMIT 7";
        $rowsD = $wpdb->get_results( $wpdb->prepare($sqlD,$args), ARRAY_A );

        wp_send_json_success([
            'total'=>$total,'by_estado'=>$by_estado,'prom_dia'=>round($prom_dia,2),
            'top_hours'=>$hours,'top_days'=>$rowsD,'from'=>$from,'to'=>$to
        ]);
    }

    public function ajax_series(){
        $wpdb = $this->guard();
        list($from_dt,$to_dt) = $this->parse_range();
        $states = $this->parse_states(); $ph = implode(',', array_fill(0,count($states),'%s'));
        $args = array_merge([$from_dt,$to_dt], $states);

        $sql = "
        SELECT DATE(s.meta_value) d, est.meta_value estado, COUNT(*) n
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} s ON (s.post_id=p.ID AND s.meta_key='fecha_hora_inicio')
        INNER JOIN {$wpdb->postmeta} est ON (est.post_id=p.ID AND est.meta_key='estado')
        WHERE p.post_type='cita_odontologia' AND p.post_status='publish'
          AND s.meta_value BETWEEN %s AND %s
          AND est.meta_value IN ($ph)
        GROUP BY d, estado ORDER BY d ASC";
        $rows = $wpdb->get_results( $wpdb->prepare($sql,$args), ARRAY_A );

        $days=[]; foreach($rows as $r){ $d=$r['d']; $st=$r['estado']; if(!isset($days[$d])) $days[$d]=array_fill_keys($states,0); $days[$d][$st]=intval($r['n']); }
        $c=strtotime(substr($from_dt,0,10)); $e=strtotime(substr($to_dt,0,10));
        while($c <= $e){ $d=date('Y-m-d',$c); if(!isset($days[$d])) $days[$d]=array_fill_keys($states,0); $c+=86400; }
        ksort($days);

        wp_send_json_success(['labels'=>array_keys($days), 'series'=>array_values($days)]);
    }

    public function ajax_export(){
        $wpdb = $this->guard();
        list($from_dt,$to_dt) = $this->parse_range();
        $states = $this->parse_states(); $ph = implode(',', array_fill(0,count($states),'%s'));
        $args = array_merge([$from_dt,$to_dt], $states);

        $sql = "
        SELECT p.ID, s.meta_value inicio, e.meta_value fin, est.meta_value estado,
               nom.meta_value nombre, ced.meta_value cedula, cor.meta_value correo, tel.meta_value telefono
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} s   ON (s.post_id=p.ID   AND s.meta_key='fecha_hora_inicio')
        LEFT  JOIN {$wpdb->postmeta} e   ON (e.post_id=p.ID   AND e.meta_key='fecha_hora_fin')
        LEFT  JOIN {$wpdb->postmeta} est ON (est.post_id=p.ID AND est.meta_key='estado')
        LEFT  JOIN {$wpdb->postmeta} nom ON (nom.post_id=p.ID AND nom.meta_key='paciente_nombre')
        LEFT  JOIN {$wpdb->postmeta} ced ON (ced.post_id=p.ID AND ced.meta_key='paciente_cedula')
        LEFT  JOIN {$wpdb->postmeta} cor ON (cor.post_id=p.ID AND cor.meta_key='paciente_correo')
        LEFT  JOIN {$wpdb->postmeta} tel ON (tel.post_id=p.ID AND tel.meta_key='paciente_telefono')
        WHERE p.post_type='cita_odontologia' AND p.post_status='publish'
          AND s.meta_value BETWEEN %s AND %s
          AND est.meta_value IN ($ph)
        ORDER BY inicio ASC";
        $rows = $wpdb->get_results( $wpdb->prepare($sql,$args), ARRAY_A );

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=asetec-odontologia-export.csv');
        $out = fopen('php://output','w');
        fputcsv($out, ['ID','Inicio','Fin','Estado','Nombre','Cédula','Correo','Teléfono']);
        foreach($rows as $r){ fputcsv($out, [$r['ID'],$r['inicio'],$r['fin'],$r['estado'],$r['nombre'],$r['cedula'],$r['correo'],$r['telefono']]); }
        fclose($out); exit;
    }
}
