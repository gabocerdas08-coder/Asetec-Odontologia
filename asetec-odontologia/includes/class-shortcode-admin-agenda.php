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
