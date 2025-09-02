<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_States {
    public static function register() {
        $statuses = [ 'pendiente', 'aprobada', 'realizada', 'cancelada_usuario', 'cancelada_admin', 'reprogramada' ];
        foreach ( $statuses as $st ) {
            register_post_status( 'odo_' . $st, [
                'label' => strtoupper( $st ),
                'public' => false,
                'internal' => true,
                'label_count' => _n_noop( ucfirst($st) . ' <span class="count">(%s)</span>', ucfirst($st) . ' <span class="count">(%s)</span>', 'asetec-odontologia' ),
            ] );
        }
    }
}
