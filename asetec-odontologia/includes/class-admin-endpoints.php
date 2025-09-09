<?php
if ( ! defined('ABSPATH') ) exit;

// Activá esto mientras depuras; luego ponelo en false
if ( ! defined('ASETEC_ODO_DEBUG') ) {
    define('ASETEC_ODO_DEBUG', true);
}

class ASETEC_ODO_Admin_Endpoints {

    public function __construct(){
        // Admin-only (agenda interna)
        add_action('wp_ajax_asetec_odo_show',         [ $this, 'ajax_show' ]);
        add_action('wp_ajax_asetec_odo_create',       [ $this, 'ajax_create' ]);
        add_action('wp_ajax_asetec_odo_update',       [ $this, 'ajax_update' ]);
        add_action('wp_ajax_asetec_odo_reschedule',   [ $this, 'ajax_reschedule' ]);
        add_action('wp_ajax_asetec_odo_approve',      [ $this, 'ajax_approve' ]);
        add_action('wp_ajax_asetec_odo_cancel',       [ $this, 'ajax_cancel' ]);
        add_action('wp_ajax_asetec_odo_mark_done',    [ $this, 'ajax_mark_done' ]);

        // Eventos para FullCalendar en agenda admin
        add_action('wp_ajax_asetec_odo_events',       [ $this, 'ajax_events' ]);
    }

    /* -------------------- Helpers -------------------- */

    private function ok($data = [], $code = 200){
        wp_send_json_success($data, $code);
    }
    private function fail($msg = 'Error', $code = 400){
        wp_send_json_error(['msg'=>$msg], $code);
    }
    private function require_nonce(){
        check_ajax_referer('asetec_odo_admin', 'nonce');
    }
    private function get_id(){
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$id && isset($_POST['post_id'])) $id = absint($_POST['post_id']);
        return $id;
    }
    private function get_cita($id){
        if (!$id) return new WP_Error('bad_id','Falta id');
        $p = get_post($id);
        if (!$p || $p->post_type !== 'cita_odontologia') return new WP_Error('not_found','Cita no encontrada');
        return $p;
    }
    private function iso($v){
        // acepta string datetime-local / iso / timestamp
        if (!$v) return '';
        if (is_numeric($v)) return date('c', (int)$v);
        $ts = strtotime($v);
        return $ts ? date('c', $ts) : '';
    }

    // Valida reglas básicas; ajusta a tus políticas si ya las aplicas en otra clase
    private function validate_window($startIso){
        // regla de “con 2 horas de anticipación”
        $min = time() + 2*60*60;
        $ts  = strtotime($startIso);
        if ($ts !== false && $ts < $min){
            return new WP_Error('too_soon', 'Debe crear con al menos 2 horas de anticipación.');
        }
        return true;
    }

    // Disponibilidad (si tienes tu propia clase utilízala aquí)
    private function check_availability($startIso, $endIso, $exclude_id = 0){
        if ( ! class_exists('ASETEC_ODO_Availability') ) return true;
        $ok = ASETEC_ODO_Availability::is_available($startIso, $endIso, $exclude_id);
        if (!$ok) return new WP_Error('no_slot', 'Ese horario ya no está disponible.');
        return true;
    }

    /* -------------------- Endpoints -------------------- */

    public function ajax_show(){
        try {
            $this->require_nonce();
            $id = $this->get_id();
            $p  = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            $data = [
                'start'              => get_post_meta($id,'fecha_hora_inicio', true),
                'end'                => get_post_meta($id,'fecha_hora_fin',    true),
                'paciente_nombre'    => get_post_meta($id,'paciente_nombre',   true),
                'paciente_cedula'    => get_post_meta($id,'paciente_cedula',   true),
                'paciente_correo'    => get_post_meta($id,'paciente_correo',   true),
                'paciente_telefono'  => get_post_meta($id,'paciente_telefono', true),
                'estado'             => get_post_meta($id,'estado',            true),
                'post_id'            => $id,
            ];
            $this->ok($data);
        } catch (Throwable $e) {
            $msg = 'Error interno';
            error_log('[ASETEC_ODO ' . __FUNCTION__ . '] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (ASETEC_ODO_DEBUG && current_user_can('manage_options')) {
                $msg .= ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            }
            $this->fail($msg, 500);
        }
    }

    public function ajax_create(){
        try{
            $this->require_nonce();
            $start = $this->iso($_POST['start'] ?? '');
            $end   = $this->iso($_POST['end']   ?? '');
            $nombre= sanitize_text_field($_POST['nombre'] ?? '');
            $ced   = sanitize_text_field($_POST['cedula'] ?? '');
            $mail  = sanitize_email($_POST['correo'] ?? '');
            $tel   = sanitize_text_field($_POST['telefono'] ?? '');
            $estado= sanitize_text_field($_POST['estado'] ?? 'pendiente');

            if (!$start || !$end || !$nombre || !$ced || !$mail || !$tel){
                return $this->fail('Datos incompletos', 400);
            }

            // Reglas
            $w = $this->validate_window($start);
            if (is_wp_error($w)) return $this->fail($w->get_error_message(), 409);

            $a = $this->check_availability($start,$end,0);
            if (is_wp_error($a)) return $this->fail($a->get_error_message(), 409);

            $id = wp_insert_post([
                'post_type'   => 'cita_odontologia',
                'post_status' => 'publish',
                'post_title'  => sprintf('%s — %s', $nombre, $start),
            ], true);
            if (is_wp_error($id)) return $this->fail('No se pudo crear la cita', 500);

            update_post_meta($id,'fecha_hora_inicio',$start);
            update_post_meta($id,'fecha_hora_fin',$end);
            update_post_meta($id,'paciente_nombre',$nombre);
            update_post_meta($id,'paciente_cedula',$ced);
            update_post_meta($id,'paciente_correo',$mail);
            update_post_meta($id,'paciente_telefono',$tel);
            update_post_meta($id,'estado',$estado);

            if (class_exists('ASETEC_ODO_Emails')){
                ASETEC_ODO_Emails::send_request_received($id);
            }

            $this->ok(['msg'=>'Cita creada','post_id'=>$id]);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_create] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_update(){
        try{
            $this->require_nonce();
            $id = $this->get_id();
            $p  = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            $start = $this->iso($_POST['start'] ?? get_post_meta($id,'fecha_hora_inicio', true));
            $end   = $this->iso($_POST['end']   ?? get_post_meta($id,'fecha_hora_fin',    true));
            $nombre= sanitize_text_field($_POST['nombre']   ?? get_post_meta($id,'paciente_nombre',   true));
            $ced   = sanitize_text_field($_POST['cedula']   ?? get_post_meta($id,'paciente_cedula',   true));
            $mail  = sanitize_email($_POST['correo']        ?? get_post_meta($id,'paciente_correo',   true));
            $tel   = sanitize_text_field($_POST['telefono'] ?? get_post_meta($id,'paciente_telefono', true));
            $estado= sanitize_text_field($_POST['estado']   ?? get_post_meta($id,'estado',            true));

            // Reglas mínimas cuando cambia horario
            $w = $this->validate_window($start);
            if (is_wp_error($w)) return $this->fail($w->get_error_message(), 409);

            $a = $this->check_availability($start,$end,$id);
            if (is_wp_error($a)) return $this->fail($a->get_error_message(), 409);

            update_post_meta($id,'fecha_hora_inicio',$start);
            update_post_meta($id,'fecha_hora_fin',$end);
            update_post_meta($id,'paciente_nombre',$nombre);
            update_post_meta($id,'paciente_cedula',$ced);
            update_post_meta($id,'paciente_correo',$mail);
            update_post_meta($id,'paciente_telefono',$tel);
            update_post_meta($id,'estado',$estado);

            $this->ok(['msg'=>'Cita actualizada']);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_update] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_reschedule(){
        try{
            $this->require_nonce();
            $id    = $this->get_id();
            $p     = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            $start = $this->iso($_POST['start'] ?? '');
            $end   = $this->iso($_POST['end']   ?? '');
            if (!$start || !$end) return $this->fail('Fechas inválidas', 400);

            $w = $this->validate_window($start);
            if (is_wp_error($w)) return $this->fail($w->get_error_message(), 409);

            $a = $this->check_availability($start,$end,$id);
            if (is_wp_error($a)) return $this->fail($a->get_error_message(), 409);

            update_post_meta($id,'fecha_hora_inicio',$start);
            update_post_meta($id,'fecha_hora_fin',$end);
            update_post_meta($id,'estado','reprogramada');

            $this->ok(['msg'=>'Cita reprogramada']);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_reschedule] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_approve(){
        try{
            $this->require_nonce();
            $id = $this->get_id();
            $p  = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            update_post_meta($id,'estado','aprobada');

            if (class_exists('ASETEC_ODO_Emails')){
                ASETEC_ODO_Emails::send_approved($id);
            }

            $this->ok(['msg'=>'Cita aprobada']);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_approve] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_cancel(){
        try{
            $this->require_nonce();
            $id = $this->get_id();
            $p  = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            update_post_meta($id,'estado','cancelada_admin');

            if (class_exists('ASETEC_ODO_Emails')){
                ASETEC_ODO_Emails::send_cancelled($id);
            }

            $this->ok(['msg'=>'Cita cancelada']);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_cancel] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_mark_done(){
        try{
            $this->require_nonce();
            $id = $this->get_id();
            $p  = $this->get_cita($id);
            if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

            update_post_meta($id,'estado','realizada');
            $this->ok(['msg'=>'Marcada como realizada']);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_mark_done] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }

    public function ajax_events(){
        try{
            $this->require_nonce();
            $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
            $end   = isset($_POST['end'])   ? sanitize_text_field($_POST['end'])   : '';

            $q = new WP_Query([
                'post_type'      => 'cita_odontologia',
                'post_status'    => 'publish',
                'posts_per_page' => 500,
                'meta_query'     => [
                    [
                        'key'     => 'fecha_hora_inicio',
                        'value'   => [$start, $end],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATETIME'
                    ]
                ]
            ]);

            $events = [];
            while($q->have_posts()){
                $q->the_post();
                $pid     = get_the_ID();
                $s       = get_post_meta($pid,'fecha_hora_inicio', true);
                $e       = get_post_meta($pid,'fecha_hora_fin',    true);
                $nombre  = get_post_meta($pid,'paciente_nombre',   true);
                $cedula  = get_post_meta($pid,'paciente_cedula',   true);
                $correo  = get_post_meta($pid,'paciente_correo',   true);
                $tel     = get_post_meta($pid,'paciente_telefono', true);
                $estado  = get_post_meta($pid,'estado',            true);

                $events[] = [
                    'id'    => (string)$pid, // compat
                    'title' => trim($nombre ? $nombre : 'Cita').' ['.$estado.']',
                    'start' => $s,
                    'end'   => $e,
                    'extendedProps' => [
                        'post_id'            => (int)$pid,
                        'estado'             => $estado,
                        'paciente_nombre'    => $nombre,
                        'paciente_cedula'    => $cedula,
                        'paciente_correo'    => $correo,
                        'paciente_telefono'  => $tel,
                    ],
                ];
            }
            wp_reset_postdata();

            $this->ok(['events'=>$events]);
        }catch(Throwable $e){
            error_log('[ASETEC_ODO ajax_events] '.$e->getMessage());
            $this->fail('Error interno', 500);
        }
    }
}

// Auto-instancia si no lo has hecho en el bootstrap
if ( ! isset($GLOBALS['asetec_odo_admin_endpoints']) ){
    $GLOBALS['asetec_odo_admin_endpoints'] = new ASETEC_ODO_Admin_Endpoints();
}
