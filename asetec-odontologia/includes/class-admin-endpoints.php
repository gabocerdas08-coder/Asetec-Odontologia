<?php
if ( ! defined('ABSPATH') ) exit;

class ASETEC_ODO_Admin_Endpoints {

  public function __construct(){
    add_action('wp_ajax_asetec_odo_events',      [ $this, 'events' ]);
    add_action('wp_ajax_asetec_odo_show',        [ $this, 'show' ]);
    add_action('wp_ajax_asetec_odo_create',      [ $this, 'create' ]);
    add_action('wp_ajax_asetec_odo_update',      [ $this, 'update' ]);
    add_action('wp_ajax_asetec_odo_reschedule',  [ $this, 'reschedule' ]);
    add_action('wp_ajax_asetec_odo_approve',     [ $this, 'approve' ]);
    add_action('wp_ajax_asetec_odo_cancel',      [ $this, 'cancel' ]);
    add_action('wp_ajax_asetec_odo_mark_done',   [ $this, 'mark_done' ]);
  }

  /* ---------- helpers ---------- */

  private function ok($data=[], $code=200){ wp_send_json_success($data, $code); }
  private function fail($msg, $code=400){ wp_send_json_error(['msg'=>$msg], $code); }
  private function nonce(){ check_ajax_referer('asetec_odo_admin','nonce'); }

  private function post_id(){
    // admitir id o post_id
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
private function to_mysql_dt($s){
    if (!$s) return '';
    // acepta: 'YYYY-mm-ddTHH:ii', 'YYYY-mm-dd HH:ii[:ss]', timestamps
    $s = trim($s);
    // normaliza ISO local -> ' ' para que no dependa de zona
    $s = str_replace('T', ' ', $s);
    // si no trae segundos, agrégalos
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $s)) {
        $s .= ':00';
    }
    $ts = is_numeric($s) ? (int)$s : strtotime($s);
    if (!$ts) return '';
    // usa hora local del servidor (no gmdate) para que no se “corra”
    return date('Y-m-d H:i:s', $ts);
}

private function validate_window($start){
    // Relaja para admin/recepción: permite crear/editar aunque falten <2h
    // Si quieres respetar estrictamente la regla, comenta el return true
    if ( current_user_can('manage_options') || current_user_can('edit_posts') ) {
        return true;
    }
    $ts = strtotime($start);
    if ($ts !== false && $ts < time() + 2*3600) {
        return new WP_Error('too_soon','Debe crear con al menos 2 horas de anticipación.');
    }
    return true;
}
private function check_availability($start,$end,$exclude=0){
    if (!class_exists('ASETEC_ODO_Availability')) return true;
    try{
        $ok = ASETEC_ODO_Availability::is_available($start,$end,$exclude);
        return $ok ? true : new WP_Error('no_slot','Ese horario ya no está disponible.');
    }catch(Throwable $e){
        error_log('[ASETEC_ODO availability] '.$e->getMessage().' ('.$start.' - '.$end.')');
        // Permitir mientras corriges el checker
        return true;
    }
}


  /* ---------- endpoints ---------- */

  public function events(){
    try{
      $this->nonce();
      $start = sanitize_text_field($_POST['start'] ?? '');
      $end   = sanitize_text_field($_POST['end'] ?? '');

      // trae muchas y filtra por fecha_inicio entre [start,end]
      $q = new WP_Query([
        'post_type'      => 'cita_odontologia',
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'meta_query'     => [
          [
            'key'     => 'fecha_hora_inicio',
            'value'   => [ $start, $end ],
            'compare' => 'BETWEEN',
            'type'    => 'DATETIME'
          ]
        ]
      ]);

      $events = [];
      while($q->have_posts()){
        $q->the_post();
        $id   = get_the_ID();
        $ini  = get_post_meta($id,'fecha_hora_inicio', true);
        $fin  = get_post_meta($id,'fecha_hora_fin',    true);
        $est  = get_post_meta($id,'estado',            true);

        $nombre  = get_post_meta($id,'paciente_nombre',   true);
        $ced     = get_post_meta($id,'paciente_cedula',   true);
        $correo  = get_post_meta($id,'paciente_correo',   true);
        $tel     = get_post_meta($id,'paciente_telefono', true);

        $events[] = [
          'id'    => (string)$id,  // 👈 importante: id = post_id
          'title' => trim(($nombre ?: 'Cita')).' ['.$est.']',
          'start' => $ini,
          'end'   => $fin,
          'extendedProps' => [
            'post_id'           => (int)$id, // 👈 y también en extendedProps
            'estado'            => $est,
            'paciente_nombre'   => $nombre,
            'paciente_cedula'   => $ced,
            'paciente_correo'   => $correo,
            'paciente_telefono' => $tel,
          ],
        ];
      }
      wp_reset_postdata();

      $this->ok([ 'events' => $events ]);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO events] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }

  public function show(){
    try{
      $this->nonce();
      $id = $this->post_id();
      $p  = $this->get_cita($id);
      if (is_wp_error($p)) return $this->fail($p->get_error_message(), 404);

      $data = [
        'post_id'           => $id,
        'start'             => get_post_meta($id,'fecha_hora_inicio', true),
        'end'               => get_post_meta($id,'fecha_hora_fin',    true),
        'paciente_nombre'   => get_post_meta($id,'paciente_nombre',   true),
        'paciente_cedula'   => get_post_meta($id,'paciente_cedula',   true),
        'paciente_correo'   => get_post_meta($id,'paciente_correo',   true),
        'paciente_telefono' => get_post_meta($id,'paciente_telefono', true),
        'estado'            => get_post_meta($id,'estado',            true),
      ];
      $this->ok($data);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO show] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }

public function create(){
  try{
    $this->nonce();

    $start  = $this->to_mysql_dt($_POST['start'] ?? '');
    $end    = $this->to_mysql_dt($_POST['end']   ?? '');
    $nombre = sanitize_text_field($_POST['nombre']   ?? '');
    $cedula = sanitize_text_field($_POST['cedula']   ?? '');
    $correo = sanitize_email      ($_POST['correo']  ?? '');
    $tel    = sanitize_text_field ($_POST['telefono']?? '');
    $estado = sanitize_text_field ($_POST['estado']  ?? 'pendiente');

    if (!$start || !$end || !$nombre || !$cedula || !$correo || !$tel) {
      return $this->fail('Datos incompletos (revise fecha/hora y datos de paciente).', 400);
    }

    // ventana
    $w = $this->validate_window($start);
    if (is_wp_error($w)) return $this->fail($w->get_error_message(), 409);

    // disponibilidad
    $a = $this->check_availability($start,$end,0);
    if (is_wp_error($a)) return $this->fail($a->get_error_message(), 409);

    $id = wp_insert_post([
      'post_type'   => 'cita_odontologia',
      'post_status' => 'publish',
      'post_title'  => $nombre.' — '.$start,
      'post_author' => get_current_user_id(), // opcional, ayuda en algunos setups
    ], true);

    if (is_wp_error($id)) {
      return $this->fail('Insert falló: '.$id->get_error_message(), 500);
    }

    update_post_meta($id,'fecha_hora_inicio',$start);
    update_post_meta($id,'fecha_hora_fin',   $end);
    update_post_meta($id,'paciente_nombre',  $nombre);
    update_post_meta($id,'paciente_cedula',  $cedula);
    update_post_meta($id,'paciente_correo',  $correo);
    update_post_meta($id,'paciente_telefono',$tel);
    update_post_meta($id,'estado',           $estado);

    // Enviar correo de solicitud recibida
    if (class_exists('ASETEC_ODO_Emails')) {
      ASETEC_ODO_Emails::send_request_received($id);
    }

    $this->ok(['msg'=>'Cita creada','post_id'=>$id]);
  }catch(Throwable $e){
    error_log('[ASETEC_ODO create] '.$e->getMessage().' | POST: '.json_encode($_POST));
    $this->fail('Error interno al crear', 500);
  }
}

public function update(){
  try{
    $this->nonce();
    $id = $this->post_id();
    $p  = $this->get_cita($id);
    if (is_wp_error($p)) return $this->fail($p->get_error_message(),404);

    $start  = $this->to_mysql_dt($_POST['start']  ?? get_post_meta($id,'fecha_hora_inicio',true));
    $end    = $this->to_mysql_dt($_POST['end']    ?? get_post_meta($id,'fecha_hora_fin',   true));
    $nombre = sanitize_text_field($_POST['nombre'] ?? get_post_meta($id,'paciente_nombre', true));
    $cedula = sanitize_text_field($_POST['cedula'] ?? get_post_meta($id,'paciente_cedula', true));
    $correo = sanitize_email      ($_POST['correo'] ?? get_post_meta($id,'paciente_correo', true));
    $tel    = sanitize_text_field ($_POST['telefono']?? get_post_meta($id,'paciente_telefono',true));
    $estado = sanitize_text_field ($_POST['estado']  ?? get_post_meta($id,'estado',true));

    if (!$start || !$end) return $this->fail('Fechas inválidas',400);

    $w = $this->validate_window($start);
    if (is_wp_error($w)) return $this->fail($w->get_error_message(),409);

    $a = $this->check_availability($start,$end,$id);
    if (is_wp_error($a)) return $this->fail($a->get_error_message(),409);

    update_post_meta($id,'fecha_hora_inicio',$start);
    update_post_meta($id,'fecha_hora_fin',   $end);
    update_post_meta($id,'paciente_nombre',  $nombre);
    update_post_meta($id,'paciente_cedula',  $cedula);
    update_post_meta($id,'paciente_correo',  $correo);
    update_post_meta($id,'paciente_telefono',$tel);
    update_post_meta($id,'estado',           $estado);

    // Enviar correo de datos actualizados
    if (class_exists('ASETEC_ODO_Emails')) {
      ASETEC_ODO_Emails::send_updated($id);
    }

    $this->ok(['msg'=>'Cita actualizada']);
  }catch(Throwable $e){
    error_log('[ASETEC_ODO update] '.$e->getMessage().' | POST: '.json_encode($_POST));
    $this->fail('Error interno al actualizar', 500);
  }
}

public function reschedule(){
    try{
      $this->nonce();
      $id = $this->post_id();
      $p  = $this->get_cita($id);
      if (is_wp_error($p)) return $this->fail($p->get_error_message(),404);

      $start = $this->to_mysql_dt($_POST['start'] ?? '');
      $end   = $this->to_mysql_dt($_POST['end']   ?? '');
      if (!$start || !$end) return $this->fail('Fechas inválidas',400);

      $w = $this->validate_window($start);
      if (is_wp_error($w)) return $this->fail($w->get_error_message(),409);

      $a = $this->check_availability($start,$end,$id);
      if (is_wp_error($a)) return $this->fail($a->get_error_message(),409);

      update_post_meta($id,'fecha_hora_inicio',$start);
      update_post_meta($id,'fecha_hora_fin',   $end);
      update_post_meta($id,'estado','reprogramada');

      // Enviar correo de cita reprogramada
      if (class_exists('ASETEC_ODO_Emails')) {
        ASETEC_ODO_Emails::send_rescheduled($id);
      }

      $this->ok(['msg'=>'Cita reprogramada']);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO reschedule] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }

  public function approve(){
    try{
      $this->nonce();
      $id = $this->post_id();
      $p  = $this->get_cita($id);
      if (is_wp_error($p)) return $this->fail($p->get_error_message(),404);

      update_post_meta($id,'estado','aprobada');

      // Enviar correo de cita aprobada
      if (class_exists('ASETEC_ODO_Emails')) {
        ASETEC_ODO_Emails::send_approved($id);
      }

      $this->ok(['msg'=>'Cita aprobada']);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO approve] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }

  public function cancel(){
    try{
      $this->nonce();
      $id = $this->post_id();
      $p  = $this->get_cita($id);
      if (is_wp_error($p)) return $this->fail($p->get_error_message(),404);

      update_post_meta($id,'estado','cancelada_admin');

      // Enviar correo de cita cancelada
      if (class_exists('ASETEC_ODO_Emails')) {
        ASETEC_ODO_Emails::send_cancelled($id);
      }

      $this->ok(['msg'=>'Cita cancelada']);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO cancel] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }

  public function mark_done(){
    try{
      $this->nonce();
      $id = $this->post_id();
      $p  = $this->get_cita($id);
      if (is_wp_error($p)) return $this->fail($p->get_error_message(),404);

      update_post_meta($id,'estado','realizada');

      // Puedes agregar un correo si lo necesitas, por ejemplo:
      // if (class_exists('ASETEC_ODO_Emails')) {
      //   ASETEC_ODO_Emails::send_done($id);
      // }

      $this->ok(['msg'=>'Marcada como realizada']);
    }catch(Throwable $e){
      error_log('[ASETEC_ODO mark_done] '.$e->getMessage());
      $this->fail('Error interno', 500);
    }
  }
}

if ( ! isset($GLOBALS['asetec_odo_admin_endpoints']) ) {
  $GLOBALS['asetec_odo_admin_endpoints'] = new ASETEC_ODO_Admin_Endpoints();
}
