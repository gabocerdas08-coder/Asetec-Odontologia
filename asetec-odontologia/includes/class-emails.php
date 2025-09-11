<?php
if ( ! defined('ABSPATH') ) exit;

class ASETEC_ODO_Emails {

    // Cambia estos defaults si ya tienes Settings:
    private static function to_admins(){
        // destinatarios internos (recepción / doctora)
        $admin = get_option('admin_email');
        // separa por coma si configuras múltiples: "recep@...,doc@..."
        $extra = get_option('asetec_odo_notify_to'); // opcional
        $list  = array_filter(array_map('trim', explode(',', (string)$extra)));
        if ($admin) array_unshift($list, $admin);
        return array_unique($list);
    }
    private static function from_name(){ return get_bloginfo('name').' – Odontología'; }
    private static function from_email(){ return get_option('admin_email'); }

    /** Utilidad: recoge datos de la cita */
    private static function load($post_id){
        $p = get_post($post_id);
        if ( ! $p || $p->post_type !== 'cita_odontologia' ) return new WP_Error('bad','Cita inválida');

        $start = get_post_meta($post_id,'fecha_hora_inicio', true);
        $end   = get_post_meta($post_id,'fecha_hora_fin',    true);
        $data  = [
            'id'                => $post_id,
            'title'             => $p->post_title,
            'estado'            => get_post_meta($post_id,'estado',            true),
            'paciente_nombre'   => get_post_meta($post_id,'paciente_nombre',   true),
            'paciente_cedula'   => get_post_meta($post_id,'paciente_cedula',   true),
            'paciente_correo'   => get_post_meta($post_id,'paciente_correo',   true),
            'paciente_telefono' => get_post_meta($post_id,'paciente_telefono', true),
            'start'             => $start,
            'end'               => $end,
            'start_fmt'         => self::fmt_local($start),
            'end_fmt'           => self::fmt_local($end),
        ];
        return $data;
    }

    /** Formatea fecha en la zona horaria WP */
    private static function fmt_local($mysql){
        if ( ! $mysql ) return '';
        $tz = wp_timezone();
        $dt = date_create($mysql, $tz);
        return $dt ? $dt->format('d/m/Y H:i') : $mysql;
    }

    /** Crea .ics (string) */
    private static function ics_content($summary, $start_mysql, $end_mysql, $uid, $status='CONFIRMED'){
        // Asegura UTC en DTSTART/DTEND para compatibilidad
        $to_utc = function($mysql){
            $ts = strtotime($mysql);
            return gmdate('Ymd\THis\Z', $ts ?: time());
        };
        $dtstart = $to_utc($start_mysql);
        $dtend   = $to_utc($end_mysql);
        $prodid  = '-//ASETEC Odontología//ES';
        $uid     = sanitize_key($uid).'@'.parse_url(home_url(), PHP_URL_HOST);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.$prodid,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'SUMMARY:'.self::escape_ics($summary),
            'DTSTART:'.$dtstart,
            'DTEND:'.$dtend,
            'STATUS:'.$status,
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines);
    }
    private static function escape_ics($text){
        return preg_replace('/([\,;])/','\\\$1', str_replace("\n", '\\n', (string)$text));
    }

    /** Envío básico HTML + adjuntos opcionales */
    private static function send_html($to, $subject, $html, $attachments = [], $bcc_admins = true){
        add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
        $headers = [];
        $from = sprintf('From: %s <%s>', self::from_name(), self::from_email());
        $headers[] = $from;

        if ($bcc_admins) {
            foreach ( self::to_admins() as $admin_to ){
                if ( is_email($admin_to) ) $headers[] = 'Bcc: '.$admin_to;
            }
        }

        $ok = wp_mail( $to, $subject, $html, $headers, $attachments );
        remove_filter('wp_mail_content_type', '__return_false'); // no afecta, por claridad
        return $ok;
    }

    /** Ya la tenías: acuse al crear solicitud */
    public static function send_request_received($post_id){
        $d = self::load($post_id);
        if ( is_wp_error($d) ) return false;

        $to = $d['paciente_correo'] ?: self::from_email();
        $subject = 'Solicitud de cita recibida – ASETEC Odontología';
        $html = '<p>Hola '.esc_html($d['paciente_nombre']).',</p>'.
                '<p>Hemos recibido tu solicitud de cita. Te confirmaremos pronto.</p>'.
                '<p><strong>Preferencia de horario:</strong> '.$d['start_fmt'].' – '.$d['end_fmt'].'</p>'.
                '<p>Estado actual: <strong>pendiente</strong></p>'.
                '<p>Gracias.</p>';
        return self::send_html($to, $subject, $html, [], true);
    }

    /** NUEVO: aprobada (+ .ics) */
    public static function send_approved($post_id){
        $d = self::load($post_id);
        if ( is_wp_error($d) ) return false;

        $to   = $d['paciente_correo'] ?: self::from_email();
        $subj = 'Cita aprobada – ASETEC Odontología';
        $html = '<p>Hola '.esc_html($d['paciente_nombre']).',</p>'.
                '<p>Tu cita fue <strong>aprobada</strong>.</p>'.
                '<p><strong>Fecha y hora:</strong> '.$d['start_fmt'].' – '.$d['end_fmt'].'</p>'.
                '<p>Te adjuntamos un archivo para añadir al calendario (.ics).</p>'.
                '<p>Nos vemos pronto.</p>';

        $ics   = self::ics_content('Cita Odontología – '.$d['paciente_nombre'], $d['start'], $d['end'], 'cita-'.$d['id'], 'CONFIRMED');
        $tmp   = wp_tempnam('cita-'.$d['id'].'.ics');
        file_put_contents($tmp, $ics);
        $ok = self::send_html($to, $subj, $html, [ $tmp ], true);
        @unlink($tmp);
        return $ok;
    }

    /** NUEVO: cancelada */
    public static function send_cancelled($post_id){
        $d = self::load($post_id);
        if ( is_wp_error($d) ) return false;

        $to   = $d['paciente_correo'] ?: self::from_email();
        $subj = 'Cita cancelada – ASETEC Odontología';
        $html = '<p>Hola '.esc_html($d['paciente_nombre']).',</p>'.
                '<p>Tu cita fue <strong>cancelada</strong>. Si necesitás reprogramar, respondé a este correo.</p>'.
                '<p>Gracias.</p>';
        return self::send_html($to, $subj, $html, [], true);
    }

    /** NUEVO: reprogramada (+ .ics) */
    public static function send_rescheduled($post_id){
        $d = self::load($post_id);
        if ( is_wp_error($d) ) return false;

        $to   = $d['paciente_correo'] ?: self::from_email();
        $subj = 'Cita reprogramada – ASETEC Odontología';
        $html = '<p>Hola '.esc_html($d['paciente_nombre']).',</p>'.
                '<p>Tu cita fue <strong>reprogramada</strong>.</p>'.
                '<p><strong>Nuevo horario:</strong> '.$d['start_fmt'].' – '.$d['end_fmt'].'</p>'.
                '<p>Adjuntamos un archivo .ics con la nueva cita.</p>';
        $ics = self::ics_content('Cita Odontología – '.$d['paciente_nombre'], $d['start'], $d['end'], 'cita-'.$d['id'].'-repro', 'CONFIRMED');
        $tmp = wp_tempnam('cita-'.$d['id'].'-repro.ics');
        file_put_contents($tmp, $ics);
        $ok = self::send_html($to, $subj, $html, [ $tmp ], true);
        @unlink($tmp);
        return $ok;
    }

    /** NUEVO: datos actualizados (sin mover horario) */
    public static function send_updated($post_id){
        $d = self::load($post_id);
        if ( is_wp_error($d) ) return false;

        $to   = $d['paciente_correo'] ?: self::from_email();
        $subj = 'Cita actualizada – ASETEC Odontología';
        $html = '<p>Hola '.esc_html($d['paciente_nombre']).',</p>'.
                '<p>Los datos de tu cita fueron <strong>actualizados</strong>.</p>'.
                '<p><strong>Horario:</strong> '.$d['start_fmt'].' – '.$d['end_fmt'].'</p>'.
                '<p>Nos vemos pronto.</p>';
        return self::send_html($to, $subj, $html, [], true);
    }
}
