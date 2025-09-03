<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Admin_Endpoints {
    private static function verify_admin(){
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'No autorizado']);
        check_ajax_referer('asetec_odo_admin','nonce');
    }

    public static function events(){
        self::verify_admin();
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end   = sanitize_text_field($_POST['end'] ?? '');
        if(!$start || !$end) wp_send_json_error(['msg'=>'Parámetros inválidos']);

        $q = new WP_Query([
            'post_type' => 'cita_odontologia',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key'=>'fecha_hora_inicio', 'compare'=>'>=', 'value'=>$start ],
                [ 'key'=>'fecha_hora_fin',    'compare'=>'<=', 'value'=>$end   ],
            ],
            'posts_per_page' => 999,
            'fields' => 'ids'
        ]);
        $events = [];
        foreach ( $q->posts as $pid ){
            $s = get_post_meta($pid,'fecha_hora_inicio',true);
            $f = get_post_meta($pid,'fecha_hora_fin',true);
            $estado = get_post_meta($pid,'estado',true);
            $pac = get_post_meta($pid,'paciente_nombre',true);
            $events[] = [
                'title' => trim(($pac?:__('Cita','asetec-odontologia')).' ['.$estado.']'),
                'start' => str_replace(' ','T',$s),
                'end'   => str_replace(' ','T',$f),
                'extendedProps' => [ 'post_id' => $pid, 'estado'=>$estado ]
            ];
        }
        wp_send_json_success([ 'events' => $events ]);
    }

    public static function approve(){
        self::verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','aprobada');
        ASETEC_ODO_Emails::send_approved_with_ics($id);
        wp_send_json_success(['msg'=>'Aprobada']);
    }

    public static function cancel(){
        self::verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','cancelada_admin');
        ASETEC_ODO_Emails::send_cancelled($id);
        wp_send_json_success(['msg'=>'Cancelada']);
    }
}

// Hooks AJAX
add_action( 'wp_ajax_asetec_odo_events',  [ 'ASETEC_ODO_Admin_Endpoints', 'events' ] );
add_action( 'wp_ajax_asetec_odo_approve', [ 'ASETEC_ODO_Admin_Endpoints', 'approve' ] );
add_action( 'wp_ajax_asetec_odo_cancel',  [ 'ASETEC_ODO_Admin_Endpoints', 'cancel' ] );
