<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Emails {

    public static function send_request_received( $post_id ) {
        $to = get_post_meta( $post_id, 'paciente_correo', true );
        if ( ! is_email($to) ) return;
        $subj = __( 'Solicitud de cita recibida', 'asetec-odontologia' );
        $msg  = sprintf(
            __( "Estimado/a %s:\n\nHemos recibido su solicitud de cita. Una vez sea aprobada, le enviaremos la confirmación con el detalle.\n\nSaludos,\nASETEC", 'asetec-odontologia' ),
            get_post_meta( $post_id, 'paciente_nombre', true )
        );
        wp_mail( $to, $subj, $msg );
        // TODO: enviar copia a recepción/doctor (configurable) y crear plantillas HTML + .ics en aprobación
    }

    /** Generar archivo .ics simple desde una cita (para usar al aprobar) */
    public static function generate_ics( $post_id ): string {
        $uid = $post_id . '@asetec-odontologia';
        $start = self::format_ics_dt( get_post_meta( $post_id, 'fecha_hora_inicio', true ) );
        $end   = self::format_ics_dt( get_post_meta( $post_id, 'fecha_hora_fin', true ) );
        $summary = 'Cita Odontología ASETEC';
        $desc = 'Cita confirmada';
        $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ASETEC//ODO//ES\r\n";
        $ics .= "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTART:$start\r\nDTEND:$end\r\nSUMMARY:$summary\r\nDESCRIPTION:$desc\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        return $ics;
    }

    private static function format_ics_dt( $dt ): string {
        // Espera 'Y-m-d H:i:s' en zona WP; convierte a UTC Zulu
        $d = ASETEC_ODO_H::to_dt( $dt );
        if ( ! $d ) $d = new DateTime( 'now', ASETEC_ODO_H::tz() );
        $d->setTimezone( new DateTimeZone('UTC') );
        return $d->format('Ymd\THis\Z');
    }
}
