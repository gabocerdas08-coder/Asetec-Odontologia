<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_FC') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda_FC {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda_fc', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets(){
        // ✅ Fijamos versión FullCalendar
        $ver = '6.1.11';

        // CDN (puedes cambiarlos por locales si tu hosting bloquea CDNs)
        $fc_css = "https://cdn.jsdelivr.net/npm/fullcalendar@$ver/index.global.min.css";
        $fc_js  = "https://cdn.jsdelivr.net/npm/fullcalendar@$ver/index.global.min.js";

        wp_register_style( 'fc-core', $fc_css, [], $ver );
        wp_register_script( 'fc-core', $fc_js, [], $ver, true );

        // Nuestro Web Component (depende de FC)
        wp_register_script(
            'asetec-odo-admin-fc',
            ASETEC_ODO_URL . 'assets/js/admin-agenda-fc.js',
            [ 'fc-core' ],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script('asetec-odo-admin-fc', 'ASETEC_ODO_ADMIN2', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'labels'=> [
                'title'     => __('Agenda Odontología', 'asetec-odontologia'),
                'new'       => __('Nueva cita', 'asetec-odontologia'),
                'search'    => __('Buscar por nombre o cédula…', 'asetec-odontologia'),
                'save'      => __('Guardar', 'asetec-odontologia'),
                'update'    => __('Actualizar', 'asetec-odontologia'),
                'approve'   => __('Aprobar', 'asetec-odontologia'),
                'cancel'    => __('Cancelar', 'asetec-odontologia'),
                'mark_done' => __('Marcar realizada', 'asetec-odontologia'),
                'close'     => __('Cerrar', 'asetec-odontologia'),
                'start'     => __('Inicio', 'asetec-odontologia'),
                'end'       => __('Fin', 'asetec-odontologia'),
                'duration'  => __('Duración (min)', 'asetec-odontologia'),
                'name'      => __('Nombre completo', 'asetec-odontologia'),
                'id'        => __('Cédula', 'asetec-odontologia'),
                'email'     => __('Correo', 'asetec-odontologia'),
                'phone'     => __('Teléfono', 'asetec-odontologia'),
                'status'    => __('Estado', 'asetec-odontologia'),
            ],
        ]);
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<p>' . esc_html__('No autorizado.', 'asetec-odontologia') . '</p>';
        }
        wp_enqueue_style('fc-core');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin-fc');

        ob_start(); ?>
        <div class="wrap modulo-asetec">
            <h2><?php echo esc_html( ASETEC_ODO_ADMIN2['labels']['title'] ?? 'Agenda' ); ?></h2>
            <asetec-odo-agenda-fc></asetec-odo-agenda-fc>
        </div>
        <?php return ob_get_clean();
    }
}

}
