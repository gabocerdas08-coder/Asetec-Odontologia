<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Cron {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_action( 'asetec_odo_cron_reminders', [ $this, 'run' ] );
    }

    /** Stub: Buscar citas aprobadas y enviar recordatorios a T-24h y T-2h */
    public function run(){
        // Ejemplo de búsqueda (implementar en iteración de recordatorios):
        // $now = new DateTime('now', ASETEC_ODO_H::tz());
        // $t24  = (clone $now)->modify('+24 hours')->format('Y-m-d H:i:s');
        // $t2   = (clone $now)->modify('+2 hours')->format('Y-m-d H:i:s');
        // Query de citas 'aprobada' en ventanas cercanas y enviar correo.
    }
}
