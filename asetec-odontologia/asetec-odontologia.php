<?php
/**
 * Plugin Name: ASETEC Odontología
 * Description: Módulo de citas odontológicas (ASETEC).
 * Version: 0.2.7
 * Author: ASETEC
 * Text Domain: asetec-odontologia
 */

if ( ! defined('ABSPATH') ) exit;

final class ASETEC_Odontologia {
    const VERSION = '0.2.7';
    const SLUG    = 'asetec-odontologia';

    private static $instance = null;
    public static function instance(){
        return self::$instance ?? ( self::$instance = new self() );
    }

    private function __construct(){
        // Define rutas
        $this->define_constants();

        // Cargar traducciones correctamente (evita “triggered too early”)
        add_action('init', [ $this, 'load_textdomain' ]);

        // Incluir clases al iniciar WP (evita fatales por carga temprana)
        add_action('init', [ $this, 'includes_safe' ], 5);
        add_action('init', [ $this, 'boot_shortcodes' ], 20);
        add_action('init', [ $this, 'boot_admin_endpoints' ], 30);
    }

    private function define_constants(){
        if ( ! defined('ASETEC_ODO_DIR') ) define('ASETEC_ODO_DIR', plugin_dir_path(__FILE__));
        if ( ! defined('ASETEC_ODO_URL') ) define('ASETEC_ODO_URL', plugin_dir_url(__FILE__));
    }

    public function load_textdomain(){
        load_plugin_textdomain('asetec-odontologia', false, dirname(plugin_basename(__FILE__)).'/languages');
    }

    /** Incluye archivos sin romper si faltan */
    public function includes_safe(){
        $files = [
            'includes/class-shortcode-reservar.php',
            'includes/class-availability.php',           // si lo usas
            'includes/class-emails.php',                 // si lo usas
            'includes/class-admin-endpoints.php',
            'includes/class-shortcode-admin-agenda.php',
            'includes/class-shortcode-dashboard.php',    // ⚠️ ESTE FALTABA
        ];
        foreach( $files as $rel ){
            $abs = ASETEC_ODO_DIR . $rel;
            if ( file_exists( $abs ) ) {
                require_once $abs;
            } else {
                error_log('[ASETEC_ODO] Falta include: '.$rel);
                // No hacemos fatal. Si es crítico, lo suplimos con un stub más abajo.
                if ( $rel === 'includes/class-shortcode-dashboard.php' && ! class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
                    // Stub mínimo para evitar fatales por referencias
                    class ASETEC_ODO_Shortcode_Dashboard {
                        public function __construct(){ add_shortcode('odo_dashboard', [ $this, 'render' ] ); }
                        public function render(){ return '<div class="wrap"><h3>Dashboard ASETEC</h3><p>Próximamente…</p></div>'; }
                    }
                    new ASETEC_ODO_Shortcode_Dashboard();
                }
            }
        }
    }

    /** Instancia de shortcodes principales si existen */
    public function boot_shortcodes(){
        if ( class_exists('ASETEC_ODO_Shortcode_Reservar') ) {
            ASETEC_ODO_Shortcode_Reservar::instance();
        }
        if ( class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {
            ASETEC_ODO_Shortcode_Admin_Agenda::instance();
        }
        if ( class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
            new ASETEC_ODO_Shortcode_Dashboard();
        }
    }

    /** Endpoints AJAX admin */
    public function boot_admin_endpoints(){
        if ( class_exists('ASETEC_ODO_Admin_Endpoints') ) {
            // Asegura una sola instancia
            if ( ! isset($GLOBALS['asetec_odo_admin_endpoints']) ) {
                $GLOBALS['asetec_odo_admin_endpoints'] = new ASETEC_ODO_Admin_Endpoints();
            }
        }
    }
}

ASETEC_Odontologia::instance();
