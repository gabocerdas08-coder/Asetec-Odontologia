<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Shortcode_Reservar {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_reservar', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_ajax_nopriv_asetec_odo_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_asetec_odo_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_asetec_odo_submit_request', [ $this, 'ajax_submit' ] );
        add_action( 'wp_ajax_asetec_odo_submit_request', [ $this, 'ajax_submit' ] );
    }

    public function assets(){
        wp_register_style( 'asetec-odo-public', ASETEC_ODO_URL . 'assets/css/public.css', [], ASETEC_Odontologia::VERSION );
        wp_register_script( 'asetec-odo-public', ASETEC_ODO_URL . 'assets/js/public.js', [ 'jquery' ], ASETEC_Odontologia::VERSION, true );
        wp_localize_script( 'asetec-odo-public', 'ASETEC_ODO', [
            'ajax'  => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'asetec_odo_public' ),
            'i18n'  => [
                'choose_date'     => __( 'Seleccione una fecha', 'asetec-odontologia' ),
                'available_slots' => __( 'Horarios disponibles', 'asetec-odontologia' ),
            ]
        ] );
    }

    public function render( $atts = [] ){
        wp_enqueue_style( 'asetec-odo-public' );
        wp_enqueue_script( 'asetec-odo-public' );
        ob_start(); ?>
        <div class="asetec-odo-reservar">
          <div class="selector-fecha">
            <label>
              <?php esc_html_e('Fecha', 'asetec-odontologia'); ?>
              <input type="date" id="odo_fecha" min="<?php echo esc_attr( date('Y-m-d') ); ?>">
            </label>
            <button class="button" id="odo_buscar"><?php esc_html_e('Buscar disponibilidad', 'asetec-odontologia'); ?></button>
          </div>
          <div id="odo_slots"></div>
          <form id="odo_form" style="display:none;" method="post">
            <h3><?php esc_html_e('Datos de contacto', 'asetec-odontologia'); ?></h3>
            <input type="hidden" name="start" id="odo_start" />
            <input type="hidden" name="end" id="odo_end" />
            <p>
              <label><?php esc_html_e('Nombre completo', 'asetec-odontologia'); ?>
                <input type="text" name="nombre" required>
              </label>
            </p>
            <p>
              <label><?php esc_html_e('Cédula', 'asetec-odontologia'); ?>
                <input type="text" name="cedula" required>
              </label>
            </p>
            <p>
              <label><?php esc_html_e('Correo', 'asetec-odontologia'); ?>
                <input type="email" name="correo" required>
              </label>
            </p>
            <p>
              <label><?php esc_html_e('Teléfono', 'asetec-odontologia'); ?>
                <input type="tel" name="telefono" required>
              </label>
            </p>
            <p>
              <label>
                <input type="checkbox" name="es_asociado" value="1">
                <?php esc_html_e('Soy asociado', 'asetec-odontologia'); ?>
              </label>
            </p>
            <p>
              <label>
                <input type="checkbox" name="es_familiar" id="chk_fam" value="1">
                <?php esc_html_e('Es un familiar directo', 'asetec-odontologia'); ?>
              </label>
            </p>
            <div id="fam_fields" style="display:none;">
              <p>
                <label><?php esc_html_e('Nombre del familiar', 'asetec-odontologia'); ?>
                  <input type="text" name="familiar_nombre">
                </label>
              </p>
              <p>
                <label><?php esc_html_e('Cédula del familiar', 'asetec-odontologia'); ?>
                  <input type="text" name="familiar_cedula">
                </label>
              </p>
              <p>
                <label><?php esc_html_e('Parentesco', 'asetec-odontologia'); ?>
                  <input type="text" name="parentesco">
                </label>
              </p>
              <p>
                <label><?php esc_html_e('Correo del familiar', 'asetec-odontologia'); ?>
                  <input type="email" name="familiar_correo">
                </label>
              </p>
              <p>
                <label><?php esc_html_e('Teléfono del familiar', 'asetec-odontologia'); ?>
                  <input type="tel" name="familiar_telefono">
                </label>
              </p>
            </div>
            <p>
              <button class="button button-primary" id="odo_submit"><?php esc_html_e('Solicitar cita', 'asetec-odontologia'); ?></button>
            </p>
          </form>
        </div>
        <?php return ob_get_clean();
    }

    public function ajax_get_slots(){
        check_ajax_referer( 'asetec_odo_public', 'nonce' );
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) wp_send_json_error(['msg'=>'Fecha no válida']);
        $slots = ASETEC_ODO_Availability::get_slots_for_date( $date );
        wp_send_json_success( [ 'slots' => $slots ] );
    }

    public function ajax_submit(){
        check_ajax_referer( 'asetec_odo_public', 'nonce' );
        $start = sanitize_text_field( $_POST['start'] ?? '' );
        $end   = sanitize_text_field( $_POST['end'] ?? '' );
        $nombre= sanitize_text_field( $_POST['nombre'] ?? '' );
        $ced   = sanitize_text_field( $_POST['cedula'] ?? '' );
        $mail  = sanitize_email( $_POST['correo'] ?? '' );
        $tel   = sanitize_text_field( $_POST['telefono'] ?? '' );
        $asoc  = ! empty($_POST['es_asociado']);
        $fam   = ! empty($_POST['es_familiar']);
        $fnom  = sanitize_text_field( $_POST['familiar_nombre'] ?? '' );
        $fced  = sanitize_text_field( $_POST['familiar_cedula'] ?? '' );
        $paren = sanitize_text_field( $_POST['parentesco'] ?? '' );
        $fmail = sanitize_email( $_POST['familiar_correo'] ?? '' );
        $ftel  = sanitize_text_field( $_POST['familiar_telefono'] ?? '' );

        if ( ! $start || ! $end || ! $nombre || ! $ced || ! $mail || ! $tel ) {
            wp_send_json_error(['msg'=>'Datos incompletos']);
        }

        // Límite de citas activas por persona
        $limit = intval( ASETEC_ODO_H::opt('max_active_per_person', 2 ) );
        $active = new WP_Query([
            'post_type' => 'cita_odontologia',
            'post_status' => 'any',
            'meta_query' => [
                'relation' => 'AND',
                [ 'key'=>'paciente_cedula', 'value'=>$ced ],
                [ 'key'=>'estado', 'value'=>['pendiente','aprobada'], 'compare'=>'IN' ]
            ],
            'fields'=>'ids'
        ]);
        if ( $active->found_posts >= $limit ) {
            wp_send_json_error(['msg'=>sprintf( __('Ya tiene %d citas activas. No es posible crear otra.', 'asetec-odontologia'), $limit )]);
        }

        // Validación de tiempo y choque
        $sdt = ASETEC_ODO_H::to_dt( $start );
        $edt = ASETEC_ODO_H::to_dt( $end );
        if ( ! $sdt || ! $edt || $edt <= $sdt ) wp_send_json_error(['msg'=>'Horario inválido']);
        $minh = intval( ASETEC_ODO_H::opt('min_hours_notice', 2) );
        $now  = new DateTime('now', ASETEC_ODO_H::tz());
        if ( $sdt < (clone $now)->modify("+{$minh} hours") ) {
            wp_send_json_error(['msg'=>sprintf(__('Debe reservar con al menos %d horas de anticipación.', 'asetec-odontologia'), $minh)]);
        }
        if ( ASETEC_ODO_Availability::slot_overlaps_appointments( $sdt, $edt ) ) {
            wp_send_json_error(['msg'=>__('Ese horario ya no está disponible.', 'asetec-odontologia')]);
        }

        // Crear cita en pendiente (bloqueo inmediato)
        $post_id = wp_insert_post([
            'post_type' => 'cita_odontologia',
            'post_status' => 'publish',
            'post_title' => sprintf( '%s %s — %s', $sdt->format('Y-m-d'), $sdt->format('H:i'), $nombre ),
        ], true );
        if ( is_wp_error( $post_id ) ) wp_send_json_error(['msg'=>'No se pudo crear la cita']);

        update_post_meta( $post_id, 'fecha_hora_inicio', ASETEC_ODO_H::fmt($sdt) );
        update_post_meta( $post_id, 'fecha_hora_fin', ASETEC_ODO_H::fmt($edt) );
        update_post_meta( $post_id, 'duracion_min', max(5, intval( ( $edt->getTimestamp() - $sdt->getTimestamp() ) / 60 )) );
        update_post_meta( $post_id, 'paciente_nombre', $nombre );
        update_post_meta( $post_id, 'paciente_cedula', $ced );
        update_post_meta( $post_id, 'paciente_correo', $mail );
        update_post_meta( $post_id, 'paciente_telefono', $tel );
        update_post_meta( $post_id, 'es_asociado', $asoc ? 1 : 0 );
        update_post_meta( $post_id, 'es_familiar', $fam ? 1 : 0 );
        if ( $fam ) {
            update_post_meta( $post_id, 'familiar_nombre', $fnom );
            update_post_meta( $post_id, 'familiar_cedula', $fced );
            update_post_meta( $post_id, 'parentesco', $paren );
            update_post_meta( $post_id, 'familiar_correo', $fmail );
            update_post_meta( $post_id, 'familiar_telefono', $ftel );
        }
        update_post_meta( $post_id, 'estado', 'pendiente' );

        // Email de acuse al solicitante (básico). En la aprobación se enviará .ics
        ASETEC_ODO_Emails::send_request_received( $post_id );

        wp_send_json_success(['msg'=>__('Su solicitud fue enviada. Recibirá confirmación por correo tras la aprobación.', 'asetec-odontologia')]);
    }
}
