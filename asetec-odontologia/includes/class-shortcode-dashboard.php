<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {

class ASETEC_ODO_Shortcode_Dashboard {

    const SLUG = 'asetec-odo-dashboard';

    /** CPTs soportados (tomamos el que tengas) */
    private $cpts = ['odo_cita','cita_odontologia'];

    public function __construct(){
        add_shortcode('odo_dashboard', [ $this, 'render' ]);
        add_action('wp_enqueue_scripts', [ $this, 'enqueue_assets' ]);

        // AJAX (logueado y no logueado)
        add_action('wp_ajax_asetec_odo_dash_data', [ $this, 'ajax_data' ]);
        add_action('wp_ajax_nopriv_asetec_odo_dash_data', [ $this, 'ajax_data' ]);
        add_action('wp_ajax_asetec_odo_dash_csv',  [ $this, 'ajax_csv' ]);
        add_action('wp_ajax_nopriv_asetec_odo_dash_csv',  [ $this, 'ajax_csv' ]);
    }

    private function can_view(){
        // Relaja permisos si lo ves en frontend con roles no-admin.
        return current_user_can('edit_posts');
    }

    public function render(){
        if ( ! $this->can_view() ) {
            return '<div class="notice notice-error"><p>No autorizado.</p></div>';
        }

        ob_start(); ?>
        <div class="odo-dash">
            <div class="odo-dash__toolbar">
                <div class="odo-field">
                    <label>Desde</label>
                    <input type="date" id="odo-from" />
                </div>
                <div class="odo-field">
                    <label>Hasta</label>
                    <input type="date" id="odo-to" />
                </div>
                <div class="odo-field grow">
                    <label>Buscar (nombre o cédula)</label>
                    <input type="text" id="odo-q" placeholder="Ej.: 1-2345-6789 o María" />
                </div>
                <div class="odo-field">
                    <label>Estados</label>
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

            <h3>Citas por día (todas las seleccionadas)</h3>
            <canvas id="odo-chart-line" height="80"></canvas>

            <h3>Distribución por estado (acumulado)</h3>
            <canvas id="odo-chart-donut" height="80"></canvas>
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

        // Chart.js por CDN (evita MIME text/html)
        wp_register_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
            [],
            '4.4.3',
            true
        );

        // JS
        wp_register_script(
            self::SLUG,
            ASETEC_ODO_URL . 'assets/js/dashboard.js',
            ['jquery','chartjs'],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script(self::SLUG, 'ASETEC_ODO_DASH', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_dash'),
            'i18n'  => [
                'loading'  => __('Cargando…','asetec-odontologia'),
                'error'    => __('Ocurrió un error','asetec-odontologia'),
            ],
        ]);

        wp_enqueue_style(self::SLUG);
        wp_enqueue_script(self::SLUG);
    }

    /** ===== helpers de meta ===== */
    private function get_start_dt($pid){
        // 1) fecha_hora_inicio (YYYY-mm-dd HH:ii:ss)
        $dt = get_post_meta($pid,'fecha_hora_inicio', true);
        if ($dt) return $dt;

        // 2) fecha + hora_inicio
        $f = get_post_meta($pid,'fecha', true);
        $h = get_post_meta($pid,'hora_inicio', true);
        if ($f && $h) return trim($f).' '.trim($h).':00';

        // 3) otros comunes
        $alt = get_post_meta($pid,'start', true);
        if ($alt) return $alt;
        $alt = get_post_meta($pid,'start_at', true);
        if ($alt) return $alt;

        return '';
    }

    private function get_end_dt($pid){
        $dt = get_post_meta($pid,'fecha_hora_fin', true);
        if ($dt) return $dt;

        $f = get_post_meta($pid,'fecha', true);
        $h = get_post_meta($pid,'hora_fin', true);
        if ($f && $h) return trim($f).' '.trim($h).':00';

        $alt = get_post_meta($pid,'end', true);
        if ($alt) return $alt;
        $alt = get_post_meta($pid,'end_at', true);
        if ($alt) return $alt;

        return '';
    }

    /** ===== AJAX DATA ===== */
    public function ajax_data(){
        // Nonce tolerante: si falta, devolvemos datos vacíos (no 400)
        $nonce = $_POST['nonce'] ?? '';
        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'asetec_odo_dash') ) {
            wp_send_json_success([
                'kpis'=>[
                    'total'=>0,'pendiente'=>0,'aprobada'=>0,'realizada'=>0,
                    'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0
                ],
                'line'=>['labels'=>[],'values'=>[]],
                'donut'=>['labels'=>[],'values'=>[]],
            ]);
        }

        $from = sanitize_text_field($_POST['from'] ?? '');
        $to   = sanitize_text_field($_POST['to'] ?? '');
        $q    = sanitize_text_field($_POST['q'] ?? '');
        $st   = isset($_POST['states']) && is_array($_POST['states'])
                ? array_map('sanitize_text_field', $_POST['states']) : [];

        // Construimos query que pruebe con ambos CPT
        $args = [
            'post_type'      => $this->cpts,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];

        // Filtro por estado y búsqueda la haremos luego en PHP (porque los meta
        // no siempre son uniformes). Primero traemos y filtramos en memoria.
        $qposts = new WP_Query($args);
        $ids = $qposts->posts;

        $kpis = [
            'total'=>0,'pendiente'=>0,'aprobada'=>0,'realizada'=>0,
            'cancelada_usuario'=>0,'cancelada_admin'=>0,'reprogramada'=>0
        ];
        $per_day = [];

        foreach ($ids as $pid){
            $estado = get_post_meta($pid,'estado', true );
            if ( ! $estado ) $estado = get_post_meta($pid,'status', true ); // alterno
            if ( $st && $estado && ! in_array($estado, $st, true) ) continue;

            // Nombre/cedula
            $nombre = get_post_meta($pid,'paciente_nombre',true);
            $cedula = get_post_meta($pid,'paciente_cedula',true);
            if ( $q ) {
                $hay = false;
                if ( $nombre && stripos($nombre, $q) !== false ) $hay = true;
                if ( $cedula && stripos($cedula, $q) !== false ) $hay = true;
                if ( ! $hay ) continue;
            }

            // fecha rango
            $inicio = $this->get_start_dt($pid);
            if ( $from && $inicio && $inicio < $from.' 00:00:00' ) continue;
            if ( $to   && $inicio && $inicio > $to.' 23:59:59' ) continue;

            $kpis['total']++;
            if ( isset($kpis[$estado]) ) $kpis[$estado]++;

            if ($inicio){
                $day = substr($inicio,0,10);
                $per_day[$day] = ($per_day[$day] ?? 0) + 1;
            }
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

    /** ===== CSV ===== */
    public function ajax_csv(){
        $nonce = $_GET['nonce'] ?? '';
        if ( empty($nonce) || ! wp_verify_nonce($nonce, 'asetec_odo_dash') ) {
            // devolvemos CSV vacío
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="citas_odontologia.csv"');
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Sin datos']);
            fclose($out);
            exit;
        }

        $from = sanitize_text_field($_GET['from'] ?? '');
        $to   = sanitize_text_field($_GET['to'] ?? '');
        $q    = sanitize_text_field($_GET['q'] ?? '');
        $st   = isset($_GET['states']) && is_array($_GET['states'])
                ? array_map('sanitize_text_field', $_GET['states']) : [];

        $args = [
            'post_type'      => $this->cpts,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ];
        $qposts = new WP_Query($args);
        $ids = $qposts->posts;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="citas_odontologia.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['ID','Fecha inicio','Fecha fin','Estado','Nombre','Cédula','Correo','Teléfono']);

        foreach ($ids as $pid){
            $estado = get_post_meta($pid,'estado', true );
            if ( ! $estado ) $estado = get_post_meta($pid,'status', true );
            if ( $st && $estado && ! in_array($estado, $st, true) ) continue;

            $nombre = get_post_meta($pid,'paciente_nombre',true);
            $cedula = get_post_meta($pid,'paciente_cedula',true);
            if ( $q ) {
                $hay=false;
                if ( $nombre && stripos($nombre,$q)!==false ) $hay=true;
                if ( $cedula && stripos($cedula,$q)!==false ) $hay=true;
                if ( ! $hay ) continue;
            }

            $inicio = $this->get_start_dt($pid);
            if ( $from && $inicio && $inicio < $from.' 00:00:00' ) continue;
            if ( $to   && $inicio && $inicio > $to.' 23:59:59' ) continue;

            fputcsv($out, [
                $pid,
                $inicio,
                $this->get_end_dt($pid),
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
}

}
