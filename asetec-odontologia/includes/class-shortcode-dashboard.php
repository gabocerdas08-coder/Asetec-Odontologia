<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {

class ASETEC_ODO_Shortcode_Dashboard {

    const SLUG = 'asetec-odo-dashboard';
    const AJAX_DATA = 'asetec_odo_dash_data_v2';
    const AJAX_CSV  = 'asetec_odo_dash_csv_v2';

    /** CPTs soportados (el principal es cita_odontologia) */
    private $cpts = ['cita_odontologia','odo_cita'];

    public function __construct(){
        add_shortcode('odo_dashboard', [ $this, 'render' ]);

        add_action('wp_enqueue_scripts', [ $this, 'enqueue_assets' ]);

        // AJAX (logueado y no logueado para evitar 400 si expira sesión)
        add_action('wp_ajax_' . self::AJAX_DATA, [ $this, 'ajax_data' ]);
        add_action('wp_ajax_nopriv_' . self::AJAX_DATA, [ $this, 'ajax_data' ]);

        add_action('wp_ajax_' . self::AJAX_CSV,  [ $this, 'ajax_csv' ]);
        add_action('wp_ajax_nopriv_' . self::AJAX_CSV,  [ $this, 'ajax_csv' ]);
    }

    private function can_view(){
        // Si quieres limitarlo a admins: return current_user_can('manage_options');
        return current_user_can('edit_posts');
    }

    public function render(){
        if ( ! $this->can_view() ) {
            return '<div class="notice notice-error"><p>No autorizado.</p></div>';
        }

        $today = current_time('Y-m-d');
        $from  = date('Y-m-d', strtotime($today.' -30 days'));

        ob_start(); ?>
        <div class="odo-dash">
            <div class="odo-dash__toolbar">
                <div class="odo-field">
                    <label><?php esc_html_e('Desde','asetec-odontologia'); ?></label>
                    <input type="date" id="odo-from" value="<?php echo esc_attr($from); ?>">
                </div>
                <div class="odo-field">
                    <label><?php esc_html_e('Hasta','asetec-odontologia'); ?></label>
                    <input type="date" id="odo-to" value="<?php echo esc_attr($today); ?>">
                </div>
                <div class="odo-field grow">
                    <label><?php esc_html_e('Buscar (nombre o cédula)','asetec-odontologia'); ?></label>
                    <input type="text" id="odo-q" placeholder="Ej.: 1-2345-6789 o María">
                </div>
                <div class="odo-field">
                    <label><?php esc_html_e('Estados','asetec-odontologia'); ?></label>
                    <div class="odo-states">
                        <label><input type="checkbox" class="st" value="pendiente" checked> Pendiente</label>
                        <label><input type="checkbox" class="st" value="aprobada" checked> Aprobada</label>
                        <label><input type="checkbox" class="st" value="realizada" checked> Realizada</label>
                        <label><input type="checkbox" class="st" value="cancelada_usuario" checked> Cancelada usuario</label>
                        <label><input type="checkbox" class="st" value="cancelada_admin" checked> Cancelada admin</label>
                        <label><input type="checkbox" class="st" value="reprogramada" checked> Reprogramada</label>
                    </div>
                </div>
                <div class="odo-field buttons">
                    <button id="odo-refresh" class="button button-primary">Actualizar</button>
                    <button id="odo-export" class="button">Exportar CSV</button>
                </div>
            </div>

            <div class="odo-kpis">
                <div class="kpi"><div class="kpi-h">Total</div><div class="kpi-v" id="k-total">0</div></div>
                <div class="kpi"><div class="kpi-h">Pendientes</div><div class="kpi-v" id="k-pend">0</div></div>
                <div class="kpi"><div class="kpi-h">Aprobadas</div><div class="kpi-v" id="k-aprob">0</div></div>
                <div class="kpi"><div class="kpi-h">Realizadas</div><div class="kpi-v" id="k-real">0</div></div>
                <div class="kpi"><div class="kpi-h">Canceladas (usuario)</div><div class="kpi-v" id="k-canu">0</div></div>
                <div class="kpi"><div class="kpi-h">Canceladas (admin)</div><div class="kpi-v" id="k-cana">0</div></div>
                <div class="kpi"><div class="kpi-h">Reprogramadas</div><div class="kpi-v" id="k-reprog">0</div></div>
            </div>

            <h3><?php esc_html_e('Citas por día (todas las seleccionadas)','asetec-odontologia'); ?></h3>
            <div class="odo-card">
              <div class="odo-chart-box" data-no-lazy="1">
                <canvas id="odo-chart-line" class="odo-chart" width="600" height="320"></canvas>
              </div>
            </div>

            <h3><?php esc_html_e('Distribución por estado (acumulado)','asetec-odontologia'); ?></h3>
            <div class="odo-card">
              <div class="odo-chart-box" data-no-lazy="1">
                <canvas id="odo-chart-donut" class="odo-chart" width="600" height="320"></canvas>
              </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_assets(){
        // CSS
        wp_register_style(
            self::SLUG,
            ASETEC_ODO_URL . 'assets/css/dashboard.css',
            [],
            ASETEC_Odontologia::VERSION
        );

        // Chart.js por CDN (evita problemas de MIME text/html)
        wp_register_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
            [],
            '4.4.3',
            true
        );

        // Nuestro JS
        wp_register_script(
            self::SLUG,
            ASETEC_ODO_URL . 'assets/js/dashboard.js',
            ['jquery','chartjs'],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script(self::SLUG, 'ASETEC_ODO_DASH', [
            'ajax'   => admin_url('admin-ajax.php'),
            'nonce'  => wp_create_nonce('asetec_odo_dash_v2'),
            'action' => self::AJAX_DATA,
            'csv'    => self::AJAX_CSV,
        ]);

        wp_enqueue_style(self::SLUG);
        wp_enqueue_script(self::SLUG);
    }

    /** ===== AJAX: datos agregados ===== */
    public function ajax_data(){
        // No matamos con 400; si nonce inválido, respondemos vacío para no romper UI.
        $nonce = $_POST['nonce'] ?? '';
        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'asetec_odo_dash_v2') ) {
            wp_send_json_success($this->empty_payload());
        }

        $from = sanitize_text_field($_POST['from'] ?? '');
        $to   = sanitize_text_field($_POST['to'] ?? '');
        $q    = sanitize_text_field($_POST['q'] ?? '');
        $st   = isset($_POST['states']) && is_array($_POST['states'])
                ? array_map('sanitize_text_field', $_POST['states']) : [];

        $meta_query = [];

        // Rango por fecha_hora_inicio si viene
        if ( $from || $to ) {
            $min = $from ? $from.' 00:00:00' : '1970-01-01 00:00:00';
            $max = $to   ? $to  .' 23:59:59' : '2999-12-31 23:59:59';
            $meta_query[] = [
                'key'     => 'fecha_hora_inicio',
                'value'   => [$min, $max],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ];
        }

        $args = [
            'post_type'      => $this->cpts,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query ?: '',
        ];

        $qposts = new WP_Query($args);
        $ids = $qposts->posts;

        $kpis = [
            'total'=>0,'pendiente'=>0,'aprobada'=>0,'realizada'=>0,
            'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0
        ];
        $per_day = [];

        foreach ($ids as $pid){
            $estado = get_post_meta($pid,'estado', true );
            if ( ! $estado ) $estado = get_post_meta($pid,'status', true );
            if ( $st && $estado && ! in_array($estado, $st, true) ) continue;

            $nombre = get_post_meta($pid,'paciente_nombre',true);
            $cedula = get_post_meta($pid,'paciente_cedula',true);
            if ( $q ) {
                $hay = false;
                if ( $nombre && stripos($nombre, $q) !== false ) $hay = true;
                if ( $cedula && stripos($cedula, $q) !== false ) $hay = true;
                if ( ! $hay ) continue;
            }

            $inicio = get_post_meta($pid,'fecha_hora_inicio',true);
            if ( ! $inicio ) continue;

            $kpis['total']++;
            if ( isset($kpis[$estado]) ) $kpis[$estado]++;

            $day = substr($inicio,0,10);
            $per_day[$day] = ($per_day[$day] ?? 0) + 1;
        }

        ksort($per_day);

        wp_send_json_success([
            'kpis'  => $kpis,
            'line'  => ['labels'=>array_keys($per_day), 'values'=>array_values($per_day)],
            'donut' => [
                'labels' => ['Pendiente','Aprobada','Realizada','Cancelada usuario','Cancelada admin','Reprogramada'],
                'values' => [
                    $kpis['pendiente'],$kpis['aprobada'],$kpis['realizada'],
                    $kpis['cancelada_usuario'],$kpis['cancelada_admin'],$kpis['reprogramada']
                ]
            ],
        ]);
    }

    /** ===== AJAX: CSV con los mismos filtros ===== */
    public function ajax_csv(){
        $nonce = $_GET['nonce'] ?? '';
        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'asetec_odo_dash_v2') ) {
            $this->csv_empty();
            exit;
        }

        $from = sanitize_text_field($_GET['from'] ?? '');
        $to   = sanitize_text_field($_GET['to'] ?? '');
        $q    = sanitize_text_field($_GET['q'] ?? '');
        $st   = isset($_GET['states']) && is_array($_GET['states'])
                ? array_map('sanitize_text_field', $_GET['states']) : [];

        $meta_query = [];
        if ( $from || $to ) {
            $min = $from ? $from.' 00:00:00' : '1970-01-01 00:00:00';
            $max = $to   ? $to  .' 23:59:59' : '2999-12-31 23:59:59';
            $meta_query[] = [
                'key'     => 'fecha_hora_inicio',
                'value'   => [$min, $max],
                'compare' => 'BETWEEN',
                'type'    => 'DATETIME',
            ];
        }

        $args = [
            'post_type'      => $this->cpts,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => $meta_query ?: '',
        ];

        $qposts = new WP_Query($args);
        $ids = $qposts->posts;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="citas_odontologia.csv"');

        $out = fopen('php://output', 'w');
        // BOM UTF-8
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['ID','Fecha inicio','Fecha fin','Estado','Nombre','Cédula','Correo','Teléfono']);

        foreach ($ids as $pid){
            $estado = get_post_meta($pid,'estado',true);
            if ( ! $estado ) $estado = get_post_meta($pid,'status',true);
            if ( $st && $estado && ! in_array($estado, $st, true) ) continue;

            $nombre = get_post_meta($pid,'paciente_nombre',true);
            $cedula = get_post_meta($pid,'paciente_cedula',true);
            if ( $q ) {
                $hay=false;
                if ( $nombre && stripos($nombre,$q)!==false ) $hay=true;
                if ( $cedula && stripos($cedula,$q)!==false ) $hay=true;
                if ( ! $hay ) continue;
            }

            $inicio = get_post_meta($pid,'fecha_hora_inicio',true);
            if ( $from && $inicio && $inicio < $from.' 00:00:00' ) continue;
            if ( $to   && $inicio && $inicio > $to  .' 23:59:59' ) continue;

            fputcsv($out, [
                $pid,
                $inicio,
                get_post_meta($pid,'fecha_hora_fin',true),
                $estado,
                $nombre,
                $cedula,
                get_post_meta($pid,'paciente_correo',true),
                get_post_meta($pid,'paciente_telefono',true),
            ]);
        }
        fclose($out);
        exit;
    }

    private function empty_payload(){
        return [
            'kpis'=>[
                'total'=>0,'pendiente'=>0,'aprobada'=>0,'realizada'=>0,
                'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0
            ],
            'line'=>['labels'=>[],'values'=>[]],
            'donut'=>['labels'=>[],'values'=>[]],
        ];
    }

    private function csv_empty(){
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="citas_odontologia.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Sin datos']);
        fclose($out);
    }
}

}
