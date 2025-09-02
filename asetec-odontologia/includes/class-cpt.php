<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_CPT {
    public static function register() {
        register_post_type( 'cita_odontologia', [
            'labels' => [
                'name' => __( 'Citas Odontología', 'asetec-odontologia' ),
                'singular_name' => __( 'Cita', 'asetec-odontologia' )
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => [ 'title', 'author' ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ] );
    }
}
