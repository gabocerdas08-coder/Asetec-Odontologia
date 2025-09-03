<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }
    private function __construct(){ add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] ); }

    public function render(){
        if ( ! current_user_can( 'manage_options' ) ) return '<p>No autorizado.</p>';
        ob_start(); ?>
        <div class="wrap">
            <h2><?php esc_html_e('Agenda administrativa (placeholder)', 'asetec-odontologia'); ?></h2>
            <p><?php esc_html_e('Aquí irá el calendario (FullCalendar) con arrastrar/soltar, creación manual y bloqueo de horas en iteración siguiente.', 'asetec-odontologia'); ?></p>
        </div>
        <?php return ob_get_clean();
    }
}
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Shortcode_Admin_Agenda {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets( $hook ){
        if ( isset($_GET['page']) && $_GET['page'] === 'asetec-odo' ) return; // no en settings
        wp_register_style( 'fc-core', ASETEC_ODO_URL.'assets/vendor/fullcalendar/fullcalendar.min.css', [], ASETEC_Odontologia::VERSION );
        wp_register_script( 'fc-core', ASETEC_ODO_URL.'assets/vendor/fullcalendar/fullcalendar.min.js', [], ASETEC_Odontologia::VERSION, true );
        wp_register_script( 'asetec-odo-admin', ASETEC_ODO_URL.'assets/js/admin-agenda.js', [ 'jquery', 'fc-core' ], ASETEC_Odontologia::VERSION, true );
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
        if ( ! current_user_can( 'manage_options' ) ) return '<p>No autorizado.</p>';
        wp_enqueue_style('fc-core');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin');
        ob_start(); ?>
        <div class="wrap">
            <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>
            <div id="odo-calendar"></div>
        </div>
        <?php return ob_get_clean();
    }
}
