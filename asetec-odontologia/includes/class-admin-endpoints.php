<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Admin_Endpoints') ) {

class ASETEC_ODO_Admin_Endpoints {

    public function __construct(){
        add_action( 'wp_ajax_asetec_odo_events',      [ $this, 'events' ] );
        add_action( 'wp_ajax_asetec_odo_approve',     [ $this, 'approve' ] );
        add_action( 'wp_ajax_asetec_odo_cancel',      [ $this, 'cancel' ] );
        add_action( 'wp_ajax_asetec_odo_mark_done',   [ $this, 'mark_done' ] );
        add_action( 'wp_ajax_asetec_odo_create',      [ $this, 'create' ] );
        add_action( 'wp_ajax_asetec_odo_reschedule',  [ $this, 'reschedule' ] );
    }

    private function verify_admin(){
        if ( ! current_user_can('manage_options') )
            wp_send_json_error(['msg'=>'No autorizado']);
        check_ajax_referer('asetec_odo_admin','nonce');
    }

    /** ------------- LISTAR EVENTOS PARA FULLCALENDAR ------------- */
    public function events(){
        $this->verify_admin();
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

    /** ------------- CREAR CITA MANUAL (desde selección) ------------- */
    public function create(){
        $this->verify_admin();
        $start  = sanitize_text_field($_POST['start'] ?? '');
        $end    = sanitize_text_field($_POST['end'] ?? '');
        $nombre = sanitize_text_field($_POST['nombre'] ?? '');
        $cedula = sanitize_text_field($_POST['cedula'] ?? '');
        $correo = sanitize_email($_POST['correo'] ?? '');
        $tel    = sanitize_text_field($_POST['telefono'] ?? '');

        if(!$start || !$end || !$nombre || !$cedula || !is_email($correo) || !$tel){
            wp_send_json_error(['msg'=>'Datos incompletos o inválidos']);
        }

        // Validaciones de choque / anticipación
        $sdt = ASETEC_ODO_H::to_dt($start);
        $edt = ASETEC_ODO_H::to_dt($end);
        if(!$sdt || !$edt || $edt <= $sdt) wp_send_json_error(['msg'=>'Rango horario inválido']);

        $minh = intval( ASETEC_ODO_H::opt('min_hours_notice', 2) );
        $now  = new DateTime('now', ASETEC_ODO_H::tz());
        if ( $sdt < (clone $now)->modify("+{$minh} hours") ) {
            wp_send_json_error(['msg'=>sprintf('Debe crear con al menos %d horas de anticipación.', $minh)]);
        }
        if ( ASETEC_ODO_Availability::slot_overlaps_appointments( $sdt, $edt ) ) {
            wp_send_json_error(['msg'=>'Ese horario ya no está disponible.']);
        }

        // Crear en estado pendiente (bloqueo inmediato, luego aprobar)
        $post_id = wp_insert_post([
            'post_type' => 'cita_odontologia',
            'post_status' => 'publish',
            'post_title' => sprintf( '%s %s — %s', $sdt->format('Y-m-d'), $sdt->format('H:i'), $nombre ),
        ], true);
        if ( is_wp_error($post_id) ) wp_send_json_error(['msg'=>'No se pudo crear la cita']);

        update_post_meta( $post_id, 'fecha_hora_inicio', ASETEC_ODO_H::fmt($sdt) );
        update_post_meta( $post_id, 'fecha_hora_fin',    ASETEC_ODO_H::fmt($edt) );
        update_post_meta( $post_id, 'duracion_min', max(5, intval( ( $edt->getTimestamp() - $sdt->getTimestamp() ) / 60 )) );
        update_post_meta( $post_id, 'paciente_nombre', $nombre );
        update_post_meta( $post_id, 'paciente_cedula', $cedula );
        update_post_meta( $post_id, 'paciente_correo', $correo );
        update_post_meta( $post_id, 'paciente_telefono', $tel );
        update_post_meta( $post_id, 'estado', 'pendiente' );

        // Opcional: avisar por correo de solicitud (si querés)
        if ( class_exists('ASETEC_ODO_Emails') ) {
            ASETEC_ODO_Emails::send_request_received( $post_id );
        }

        wp_send_json_success(['id'=>$post_id, 'estado'=>'pendiente']);
    }

    /** ------------- APROBAR ------------- */
    public function approve(){
        $this->verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','aprobada');
        if ( class_exists('ASETEC_ODO_Emails') ) {
            ASETEC_ODO_Emails::send_approved_with_ics($id);
        }
        wp_send_json_success(['msg'=>'Aprobada']);
    }

    /** ------------- CANCELAR ------------- */
    public function cancel(){
        $this->verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','cancelada_admin');
        if ( class_exists('ASETEC_ODO_Emails') ) {
            ASETEC_ODO_Emails::send_cancelled($id);
        }
        wp_send_json_success(['msg'=>'Cancelada']);
    }

    /** ------------- MARCAR REALIZADA ------------- */
    public function mark_done(){
        $this->verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','realizada');
        wp_send_json_success(['msg'=>'Marcada como realizada']);
    }

    /** ------------- REPROGRAMAR (drag/resize) ------------- */
    public function reschedule(){
        $this->verify_admin();
        $id    = intval($_POST['id'] ?? 0);
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end   = sanitize_text_field($_POST['end'] ?? '');
        if(!$id || !$start || !$end) wp_send_json_error(['msg'=>'Parámetros inválidos']);

        $sdt = ASETEC_ODO_H::to_dt($start);
        $edt = ASETEC_ODO_H::to_dt($end);
        if(!$sdt || !$edt || $edt <= $sdt) wp_send_json_error(['msg'=>'Rango horario inválido']);

        // Evitar choque con otras citas
        if ( ASETEC_ODO_Availability::slot_overlaps_appointments( $sdt, $edt ) ) {
            wp_send_json_error(['msg'=>'Ese horario se cruza con otra cita.']);
        }

        update_post_meta($id,'fecha_hora_inicio', ASETEC_ODO_H::fmt($sdt));
        update_post_meta($id,'fecha_hora_fin', ASETEC_ODO_H::fmt($edt));
        update_post_meta($id,'duracion_min', max(5, intval( ( $edt->getTimestamp() - $sdt->getTimestamp() ) / 60 )) );

        // Si estaba aprobada, marcamos como reprogramada (puedes dejar aprobada si prefieres)
        $estado = get_post_meta($id,'estado',true);
        if ( in_array($estado, ['aprobada','pendiente'], true) ) {
            update_post_meta($id,'estado','reprogramada');
        }

        wp_send_json_success(['msg'=>'Reprogramada']);
    }
}

// Instanciar una sola vez
new ASETEC_ODO_Admin_Endpoints();

} // class_exists
