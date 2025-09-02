<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Dashboard {
    public function __construct(){
        add_action('wp_ajax_asetec_odo_stats', [ $this, 'stats' ] );
        add_action('wp_ajax_asetec_odo_export_csv', [ $this, 'export_csv' ] );
    }

    public static function query_citas( $from, $to, $estado = '' ){
        $meta = [
            [ 'key'=>'fecha_hora_inicio', 'compare'=>'>=', 'value'=>$from ],
            [ 'key'=>'fecha_hora_fin',    'compare'=>'<=', 'value'=>$to   ],
        ];
        if ( $estado ) $meta[] = [ 'key'=>'estado', 'value'=>$estado ];
        return new WP_Query([
            'post_type'=>'cita_odontologia', 'post_status'=>'any', 'meta_query'=>$meta, 'posts_per_page'=>-1
        ]);
    }

    public function stats(){
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'No autorizado']);
        check_ajax_referer('asetec_odo_admin','nonce');
        $from = sanitize_text_field($_POST['from'] ?? date('Y-m-01 00:00:00'));
        $to   = sanitize_text_field($_POST['to']   ?? date('Y-m-t 23:59:59'));
        $estados = [ 'pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada' ];
        $data = [];
        foreach ( $estados as $e ){
            $q = self::query_citas($from,$to,$e);
            $data[$e] = $q->found_posts;
        }
        wp_send_json_success(['data'=>$data]);
    }

    public function export_csv(){
        if ( ! current_user_can('manage_options') ) exit;
        check_ajax_referer('asetec_odo_admin','nonce');
        $from = sanitize_text_field($_GET['from'] ?? date('Y-m-01 00:00:00'));
        $to   = sanitize_text_field($_GET['to']   ?? date('Y-m-t 23:59:59'));
        $q = self::query_citas($from,$to);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte-citas.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, ['ID','Inicio','Fin','Estado','Nombre','Cédula','Correo','Teléfono']);
        foreach( $q->posts as $p ){
            $pid = $p->ID;
            fputcsv($out, [
                $pid,
                get_post_meta($pid,'fecha_hora_inicio',true),
                get_post_meta($pid,'fecha_hora_fin',true),
                get_post_meta($pid,'estado',true),
                get_post_meta($pid,'paciente_nombre',true),
                get_post_meta($pid,'paciente_cedula',true),
                get_post_meta($pid,'paciente_correo',true),
                get_post_meta($pid,'paciente_telefono',true),
            ]);
        }
        fclose($out);
        exit;
    }
}
new ASETEC_ODO_Dashboard();