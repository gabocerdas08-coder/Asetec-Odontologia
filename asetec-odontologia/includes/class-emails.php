<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Emails {

    public static function send_request_received( $post_id ) {
        $to = get_post_meta( $post_id, 'paciente_correo', true );
        if ( ! is_email($to) ) return;
        $subject = __( 'Solicitud de cita recibida', 'asetec-odontologia' );
        $body    = self::render_template('email-solicitud.php', [ 'post_id'=>$post_id ]);
        self::mail_html( $to, $subject, $body );
    }

    public static function send_approved_with_ics( $post_id ){
        $to = get_post_meta( $post_id, 'paciente_correo', true );
        if ( ! is_email($to) ) return;
        $subject = __( 'Cita aprobada — Odontología ASETEC', 'asetec-odontologia' );
        $body    = self::render_template('email-aprobada.php', [ 'post_id'=>$post_id ]);

        // Generar ICS temporal en uploads
        $ics = self::generate_ics( $post_id );
        $upload_dir = wp_upload_dir();
        $ics_path = trailingslashit($upload_dir['basedir']).'cita-'.$post_id.'.ics';
        file_put_contents($ics_path, $ics);

        self::mail_html( $to, $subject, $body, [ $ics_path ] );
    }

    public static function send_cancelled( $post_id ){
        $to = get_post_meta( $post_id, 'paciente_correo', true );
        if ( ! is_email($to) ) return;
        $subject = __( 'Cita cancelada — Odontología ASETEC', 'asetec-odontologia' );
        $body    = self::render_template('email-cancelada.php', [ 'post_id'=>$post_id ]);
        self::mail_html( $to, $subject, $body );
    }

    // Utilitarios
    public static function mail_html( $to, $subject, $html, $attachments = [] ){
        add_filter('wp_mail_content_type', function(){ return 'text/html'; });
        wp_mail( $to, $subject, $html, [], $attachments );
        remove_all_filters('wp_mail_content_type');
    }

    public static function render_template( $file, $vars = [] ){
        $path = ASETEC_ODO_DIR . 'templates/' . $file;
        if ( ! file_exists($path) ) return '';
        ob_start(); extract( $vars, EXTR_SKIP ); include $path; return ob_get_clean();
    }

    public static function generate_ics( $post_id ): string {
        $uid = $post_id . '@asetec-odontologia';
        $start = self::format_ics_dt( get_post_meta( $post_id, 'fecha_hora_inicio', true ) );
        $end   = self::format_ics_dt( get_post_meta( $post_id, 'fecha_hora_fin', true ) );
        $summary = 'Cita Odontología ASETEC';
        $pac = get_post_meta($post_id,'paciente_nombre',true);
        $desc = 'Paciente: '. $pac;
        $ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ASETEC//ODO//ES\r\n";
        $ics .= "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTART:$start\r\nDTEND:$end\r\nSUMMARY:$summary\r\nDESCRIPTION:$desc\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        return $ics;
    }

    private static function format_ics_dt( $dt ): string {
        $d = ASETEC_ODO_H::to_dt( $dt );
        if ( ! $d ) $d = new DateTime( 'now', ASETEC_ODO_H::tz() );
        $d->setTimezone( new DateTimeZone('UTC') );
        return $d->format('Ymd\THis\Z');
    }
}
