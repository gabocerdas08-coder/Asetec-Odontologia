<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
class ASETEC_ODO_Shortcode_Dashboard {
    public function __construct(){
        add_shortcode('odo_dashboard', [ $this, 'render' ]);
        // Si quieres assets propios, podrías encolar aquí, pero mantenemos simple.
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<p>No autorizado.</p>';
        }
        // Placeholder simple: evita fatales y te da un shortcode funcional
        ob_start(); ?>
        <div class="wrap">
            <h2><?php esc_html_e('Dashboard Odontología', 'asetec-odontologia'); ?></h2>
            <p><?php esc_html_e('Próximamente aquí verás KPIs y reportes.', 'asetec-odontologia'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
}
