<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Cron {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_action( 'asetec_odo_cron_reminders', [ $this, 'run' ] );
    }

    public function run(){
        // TODO: Implementar recordatorios T-24h y T-2h (citas 'aprobada')
        // Buscar por rango con WP_Query y enviar emails.
    }
}
