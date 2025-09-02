<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Settings {
    private static $instance = null;
    public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }

    public static function bootstrap_defaults() {
        if ( ! get_option( 'asetec_odo_settings' ) ) {
            $defaults = [
                'schedule' => [
                    '1' => [ [ 'start' => '08:00', 'end' => '12:00', 'duration' => 40 ], [ 'start' => '13:00', 'end' => '17:00', 'duration' => 40 ] ],
                    '2' => [ [ 'start' => '08:00', 'end' => '12:00', 'duration' => 40 ], [ 'start' => '13:00', 'end' => '17:00', 'duration' => 40 ] ],
                    '3' => [ [ 'start' => '08:00', 'end' => '12:00', 'duration' => 40 ], [ 'start' => '13:00', 'end' => '17:00', 'duration' => 40 ] ],
                    '4' => [ [ 'start' => '08:00', 'end' => '12:00', 'duration' => 40 ], [ 'start' => '13:00', 'end' => '17:00', 'duration' => 40 ] ],
                    '5' => [ [ 'start' => '08:00', 'end' => '12:00', 'duration' => 40 ], [ 'start' => '13:00', 'end' => '17:00', 'duration' => 40 ] ],
                    '6' => [], '7' => []
                ],
                'allowed_durations' => [ 20, 30, 40, 60 ],
                'min_hours_notice' => 2,
                'max_active_per_person' => 2,
                'blocked_dates' => [],
            ];
            add_option( 'asetec_odo_settings', $defaults );
        }
    }

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_post_asetec_odo_save_settings', [ $this, 'save' ] );
    }

    public function menu() {
        add_menu_page(
            __( 'Odontología', 'asetec-odontologia' ),
            __( 'Odontología', 'asetec-odontologia' ),
            'manage_options',
            'asetec-odo',
            [ $this, 'render' ],
            'dashicons-smiley',
            25
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $opts = get_option( 'asetec_odo_settings', [] );
        $schedule = $opts['schedule'] ?? [];
        $allowed = $opts['allowed_durations'] ?? [20,30,40,60];
        $minh    = intval( $opts['min_hours_notice'] ?? 2 );
        $limit   = intval( $opts['max_active_per_person'] ?? 2 );
        $blocked = $opts['blocked_dates'] ?? [];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración Odontología', 'asetec-odontologia'); ?></h1>
            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                <?php wp_nonce_field( 'asetec_odo_save_settings', 'asetec_odo_nonce' ); ?>
                <input type="hidden" name="action" value="asetec_odo_save_settings" />

                <h2><?php esc_html_e('Horario base por día (intervalos y duración por tramo)', 'asetec-odontologia'); ?></h2>
                <p class="description"><?php esc_html_e('Agregue múltiples intervalos por día y defina la duración por defecto para cada tramo.', 'asetec-odontologia'); ?></p>
                <table class="widefat">
                    <thead><tr><th><?php esc_html_e('Día', 'asetec-odontologia'); ?></th><th><?php esc_html_e('Tramos', 'asetec-odontologia'); ?></th></tr></thead>
                    <tbody>
                        <?php
                        $days = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
                        foreach ( $days as $dnum => $dname ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $dname ); ?></strong></td>
                            <td>
                                <div class="asetec-odo-tramos" data-day="<?php echo esc_attr($dnum); ?>">
                                    <?php $tramos = $schedule[ strval($dnum) ] ?? []; if ( empty($tramos) ) $tramos = []; ?>
                                    <?php foreach ( $tramos as $idx => $row ) : ?>
                                        <div class="tramo-row" style="display:flex;gap:12px;align-items:center;margin:8px 0;">
                                            <label>Inicio <input type="time" name="schedule[<?php echo esc_attr($dnum); ?>][<?php echo esc_attr($idx); ?>][start]" value="<?php echo esc_attr( $row['start'] ?? '' ); ?>" required></label>
                                            <label>Fin <input type="time" name="schedule[<?php echo esc_attr($dnum); ?>][<?php echo esc_attr($idx); ?>][end]" value="<?php echo esc_attr( $row['end'] ?? '' ); ?>" required></label>
                                            <label>Duración (min) <input type="number" min="5" step="5" name="schedule[<?php echo esc_attr($dnum); ?>][<?php echo esc_attr($idx); ?>][duration]" value="<?php echo esc_attr( intval($row['duration'] ?? 40) ); ?>" required></label>
                                            <button class="button remove-tramo" type="button">&times;</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button add-tramo" data-day="<?php echo esc_attr($dnum); ?>"><?php esc_html_e('Agregar tramo', 'asetec-odontologia'); ?></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2><?php esc_html_e('Duraciones permitidas (creación manual)', 'asetec-odontologia'); ?></h2>
                <input type="text" class="regular-text" name="allowed_durations" value="<?php echo esc_attr( implode(',', array_map('intval',$allowed)) ); ?>" />
                <p class="description"><?php esc_html_e('Lista separada por comas (minutos), p. ej.: 20,30,40,60', 'asetec-odontologia'); ?></p>

                <h2><?php esc_html_e('Reglas', 'asetec-odontologia'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Anticipación mínima (horas)', 'asetec-odontologia'); ?></th>
                        <td><input type="number" min="0" name="min_hours_notice" value="<?php echo esc_attr( $minh ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Máx. de citas activas por persona', 'asetec-odontologia'); ?></th>
                        <td><input type="number" min="1" name="max_active_per_person" value="<?php echo esc_attr( $limit ); ?>" /></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Fechas bloqueadas (YYYY-mm-dd)', 'asetec-odontologia'); ?></h2>
                <textarea name="blocked_dates" rows="4" class="large-text" placeholder="2025-09-14&#10;2025-10-05"><?php echo esc_textarea( implode("\n", $blocked ) ); ?></textarea>

                <?php submit_button( __( 'Guardar configuración', 'asetec-odontologia' ) ); ?>
            </form>
        </div>
        <script>
        (function(){
            document.querySelectorAll('.add-tramo').forEach(btn=>{
                btn.addEventListener('click', function(){
                    const day = this.getAttribute('data-day');
                    const wrap = document.querySelector('.asetec-odo-tramos[data-day="'+day+'"]');
                    const idx  = wrap.querySelectorAll('.tramo-row').length;
                    const html = `
                    <div class="tramo-row" style="display:flex;gap:12px;align-items:center;margin:8px 0;">
                        <label>Inicio <input type="time" name="schedule[${day}][${idx}][start]" required></label>
                        <label>Fin <input type="time" name="schedule[${day}][${idx}][end]" required></label>
                        <label>Duración (min) <input type="number" min="5" step="5" name="schedule[${day}][${idx}][duration]" value="40" required></label>
                        <button class="button remove-tramo" type="button">&times;</button>
                    </div>`;
                    wrap.insertAdjacentHTML('beforeend', html);
                });
            });
            document.addEventListener('click', function(e){
                if ( e.target && e.target.classList.contains('remove-tramo') ) {
                    e.preventDefault();
                    e.target.closest('.tramo-row').remove();
                }
            });
        })();
        </script>
        <?php
    }

    public function save() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die('Forbidden');
        if ( ! isset($_POST['asetec_odo_nonce']) || ! wp_verify_nonce( $_POST['asetec_odo_nonce'], 'asetec_odo_save_settings' ) ) wp_die('Bad nonce');

        $schedule = $_POST['schedule'] ?? [];
        $clean = [];
        foreach ( $schedule as $day => $tramos ) {
            $day = strval( intval( $day ) );
            $clean[$day] = [];
            if ( is_array( $tramos ) ) {
                foreach ( $tramos as $t ) {
                    $s = isset($t['start']) ? sanitize_text_field($t['start']) : '';
                    $e = isset($t['end']) ? sanitize_text_field($t['end']) : '';
                    $d = isset($t['duration']) ? intval($t['duration']) : 40;
                    if ( $s && $e && $d > 0 ) {
                        $clean[$day][] = [ 'start' => $s, 'end' => $e, 'duration' => $d ];
                    }
                }
            }
        }

        $allowed = isset($_POST['allowed_durations']) ? array_filter( array_map('intval', explode(',', sanitize_text_field($_POST['allowed_durations'])) ) ) : [20,30,40,60];
        $minh    = isset($_POST['min_hours_notice']) ? max(0, intval($_POST['min_hours_notice'])) : 2;
        $limit   = isset($_POST['max_active_per_person']) ? max(1, intval($_POST['max_active_per_person'])) : 2;
        $blocked = isset($_POST['blocked_dates']) ? array_filter( array_map( 'trim', explode("\n", wp_unslash($_POST['blocked_dates']) ) ) ) : [];

        ASETEC_ODO_H::update_opts([
            'schedule' => $clean,
            'allowed_durations' => array_values( $allowed ),
            'min_hours_notice' => $minh,
            'max_active_per_person' => $limit,
            'blocked_dates' => $blocked,
        ]);

        wp_safe_redirect( add_query_arg( ['page'=>'asetec-odo','updated'=>'true'], admin_url('admin.php') ) );
        exit;
    }
}
