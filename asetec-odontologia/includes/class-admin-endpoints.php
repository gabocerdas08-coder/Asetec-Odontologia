<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Admin_Endpoints') ) {

class ASETEC_ODO_Admin_Endpoints {

    public function __construct(){
        add_action( 'wp_ajax_asetec_odo_events',      [ $this, 'events' ] );
        add_action( 'wp_ajax_asetec_odo_show',        [ $this, 'show' ] );
        add_action( 'wp_ajax_asetec_odo_create',      [ $this, 'create' ] );
        add_action( 'wp_ajax_asetec_odo_approve',     [ $this, 'approve' ] );
        add_action( 'wp_ajax_asetec_odo_cancel',      [ $this, 'cancel' ] );
        add_action( 'wp_ajax_asetec_odo_mark_done',   [ $this, 'mark_done' ] );
        add_action( 'wp_ajax_asetec_odo_reschedule',  [ $this, 'reschedule' ] );
    }

    private function verify_admin(){
        if ( ! current_user_can('manage_options') )
            wp_send_json_error(['msg'=>'No autorizado']);
        check_ajax_referer('asetec_odo_admin','nonce');
    }

    private function iso_to_local( $iso ){
        try {
            $tz = ASETEC_ODO_H::tz();
            $dt = new DateTime( $iso );
            $dt->setTimezone( $tz );
            return $dt->format('Y-m-d H:i:s');
        } catch ( Exception $e ){
            return '';
        }
    }

    private function overlap_exists( DateTime $s, DateTime $f, $exclude_id = 0 ): bool {
        $args = [
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                // start < range_end
                [ 'key'=>'fecha_hora_inicio', 'compare'=>'<', 'value'=> ASETEC_ODO_H::fmt($f), 'type'=>'DATETIME' ],
                // end   > range_start
                [ 'key'=>'fecha_hora_fin',    'compare'=>'>', 'value'=> ASETEC_ODO_H::fmt($s), 'type'=>'DATETIME' ],
                // solo pendientes/aprobadas/reprogramadas bloquean
                [ 'key'=>'estado', 'value'=> ['pendiente','aprobada','reprogramada'], 'compare'=>'IN' ],
            ],
        ];
        if ( $exclude_id ) $args['post__not_in'] = [ intval($exclude_id) ];
        $q = new WP_Query( $args );
        return $q->have_posts();
    }

    /** --------- LISTAR EVENTOS (rango) --------- */
    public function events(){
        $this->verify_admin();
        $startIso = sanitize_text_field($_POST['start'] ?? '');
        $endIso   = sanitize_text_field($_POST['end'] ?? '');
        if(!$startIso || !$endIso) wp_send_json_success([ 'events' => [] ]); // no bloqueamos UI

        $rangeStart = $this->iso_to_local( $startIso );
        $rangeEnd   = $this->iso_to_local( $endIso );

        // Traer cualquier cita que se solape con el rango (no solo las contenidas)
        $q = new WP_Query([
            'post_type'      => 'cita_odontologia',
            'post_status'    => 'any',
            'posts_per_page' => 999,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'AND',
                [ 'key'=>'fecha_hora_inicio', 'compare'=>'<', 'value'=>$rangeEnd,   'type'=>'DATETIME' ],
                [ 'key'=>'fecha_hora_fin',    'compare'=>'>', 'value'=>$rangeStart, 'type'=>'DATETIME' ],
            ],
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

    /** --------- MOSTRAR DETALLE --------- */
    public function show(){
        $this->verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        $d = [
            'start'   => str_replace(' ','T', get_post_meta($id,'fecha_hora_inicio',true)),
            'end'     => str_replace(' ','T', get_post_meta($id,'fecha_hora_fin',true)),
            'estado'  => get_post_meta($id,'estado',true),
            'nombre'  => get_post_meta($id,'paciente_nombre',true),
            'cedula'  => get_post_meta($id,'paciente_cedula',true),
            'correo'  => get_post_meta($id,'paciente_correo',true),
            'telefono'=> get_post_meta($id,'paciente_telefono',true),
        ];
        wp_send_json_success($d);
    }

    /** --------- CREAR (bloqueo inmediato) --------- */
    public function create(){
        $this->verify_admin();
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end   = sanitize_text_field($_POST['end'] ?? '');
        $nombre= sanitize_text_field($_POST['nombre'] ?? '');
        $ced   = sanitize_text_field($_POST['cedula'] ?? '');
        $mail  = sanitize_email($_POST['correo'] ?? '');
        $tel   = sanitize_text_field($_POST['telefono'] ?? '');

        if(!$start || !$end || !$nombre || !$ced || !is_email($mail) || !$tel){
            wp_send_json_error(['msg'=>'Datos incompletos o inválidos']);
        }

        $sdt = ASETEC_ODO_H::to_dt( $start );
        $edt = ASETEC_ODO_H::to_dt( $end );
        if(!$sdt || !$edt || $edt <= $sdt) wp_send_json_error(['msg'=>'Rango horario inválido']);

        $minh = intval( ASETEC_ODO_H::opt('min_hours_notice', 2) );
        $now  = new DateTime('now', ASETEC_ODO_H::tz());
        if ( $sdt < (clone $now)->modify("+{$minh} hours") ) {
            wp_send_json_error(['msg'=>sprintf('Debe crear con al menos %d horas de anticipación.', $minh)]);
        }

        if ( $this->overlap_exists( $sdt, $edt ) ) {
            wp_send_json_error(['msg'=>'Ese horario ya no está disponible.']);
        }

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
        update_post_meta( $post_id, 'paciente_cedula', $ced );
        update_post_meta( $post_id, 'paciente_correo', $mail );
        update_post_meta( $post_id, 'paciente_telefono', $tel );
        update_post_meta( $post_id, 'estado', 'pendiente' );

        if ( class_exists('ASETEC_ODO_Emails') ) {
            ASETEC_ODO_Emails::send_request_received( $post_id );
        }

        wp_send_json_success(['id'=>$post_id, 'estado'=>'pendiente']);
    }

    /** --------- APROBAR --------- */
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

    /** --------- CANCELAR --------- */
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

    /** --------- MARCAR REALIZADA --------- */
    public function mark_done(){
        $this->verify_admin();
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error(['msg'=>'ID inválido']);
        update_post_meta($id,'estado','realizada');
        wp_send_json_success(['msg'=>'Marcada como realizada']);
    }

    /** --------- REPROGRAMAR --------- */
    public function reschedule(){
        $this->verify_admin();
        $id    = intval($_POST['id'] ?? 0);
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end   = sanitize_text_field($_POST['end'] ?? '');
        if(!$id || !$start || !$end) wp_send_json_error(['msg'=>'Parámetros inválidos']);

        $sdt = ASETEC_ODO_H::to_dt($start);
        $edt = ASETEC_ODO_H::to_dt($end);
        if(!$sdt || !$edt || $edt <= $sdt) wp_send_json_error(['msg'=>'Rango horario inválido']);

        if ( $this->overlap_exists( $sdt, $edt, $id ) ) {
            wp_send_json_error(['msg'=>'Ese horario se cruza con otra cita.']);
        }

        update_post_meta($id,'fecha_hora_inicio', ASETEC_ODO_H::fmt($sdt));
        update_post_meta($id,'fecha_hora_fin', ASETEC_ODO_H::fmt($edt));
        update_post_meta($id,'duracion_min', max(5, intval( ( $edt->getTimestamp() - $sdt->getTimestamp() ) / 60 )) );

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
