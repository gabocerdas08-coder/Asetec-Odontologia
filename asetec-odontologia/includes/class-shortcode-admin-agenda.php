<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
        // Cargar assets tanto en admin como en frontend
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
    }

    public function assets( $hook = '' ){
        // Evitar la página de settings del plugin
        if ( is_admin() && isset($_GET['page']) && $_GET['page'] === 'asetec-odo' ) return;

        // FullCalendar por CDN (si prefieres locales, cambia estas URLs)
        wp_register_style( 'fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css', [], '6.1.15' );
        wp_register_script('fc-core', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true );

        // Script propio
        wp_register_script(
            'asetec-odo-admin',
            ASETEC_ODO_URL.'assets/js/admin-agenda.js',
            [ 'jquery', 'fc-core' ],
            ASETEC_Odontologia::VERSION,
            true
        );
        wp_localize_script( 'asetec-odo-admin', 'ASETEC_ODO_ADMIN', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'i18n'  => [
                'confirm_approve' => __('¿Desea aprobar esta cita?', 'asetec-odontologia'),
                'confirm_cancel'  => __('¿Desea cancelar esta cita?', 'asetec-odontologia'),
            ]
        ] );
    }

    public function render(){
        // Mantén el control de acceso
        if ( ! current_user_can( 'manage_options' ) )
            return '<p>No autorizado.</p>';

        wp_enqueue_style('fc-core');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');

        ob_start(); ?>
        <div class="wrap modulo-asetec">
            <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>
            <div id="odo-calendar" style="min-height:600px"></div>
        </div>
        <?php return ob_get_clean();
    }
}

} // class_exists
