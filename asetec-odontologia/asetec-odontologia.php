<?php
/**
 * Plugin Name: ASETEC Odontología
 * Description: Gestión de citas odontológicas (slots editables, reservas, agenda admin, dashboard y correos .ics).
 * Version: 0.2.6
 * Author: ASETEC
 * Text Domain: asetec-odontologia
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class ASETEC_Odontologia {
    const VERSION = '0.2.6';
    private static $instance = null;

    public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct() {
        $this->define_constants();
        $this->includes();                 // sólo define rutas y require_once
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
    }

    private function define_constants() {
        if ( ! defined('ASETEC_ODO_FILE') ) define( 'ASETEC_ODO_FILE', __FILE__ );
        if ( ! defined('ASETEC_ODO_DIR') )  define( 'ASETEC_ODO_DIR', plugin_dir_path( __FILE__ ) );
        if ( ! defined('ASETEC_ODO_URL') )  define( 'ASETEC_ODO_URL', plugin_dir_url( __FILE__ ) );
    }

    private function safe_include( $rel ) {
        $path = ASETEC_ODO_DIR . ltrim( $rel, '/' );
        if ( file_exists( $path ) ) require_once $path;
        else error_log( '[ASETEC_ODO] Falta include: ' . $rel );
    }

    private function includes() {
        // Utilidades base (si faltan, todo lo demás puede fallar)
        $this->safe_include('includes/helpers.php');
        $this->safe_include('includes/class-cpt.php');
        $this->safe_include('includes/class-states.php');
        $this->safe_include('includes/class-settings.php');
        $this->safe_include('includes/class-availability.php');

        // Emails ANTES de endpoints que los llaman
        $this->safe_include('includes/class-emails.php');

        // Cron (no envía nada aún, pero define el hook)
        $this->safe_include('includes/class-cron.php');

        // Shortcodes público y admin
        $this->safe_include('includes/class-shortcode-reservar.php');
        $this->safe_include('includes/class-shortcode-admin-agenda.php');

        // Endpoints AJAX admin (depende de emails)
        $this->safe_include('includes/class-admin-endpoints.php');

        // Dashboard / reportes
        $this->safe_include('includes/class-dashboard.php');
        $this->safe_include('includes/class-shortcode-dashboard.php');
    }

    public function init() {
        if ( class_exists('ASETEC_ODO_CPT') )    ASETEC_ODO_CPT::register();
        if ( class_exists('ASETEC_ODO_States') ) ASETEC_ODO_States::register();
    }

    public function plugins_loaded() {
        load_plugin_textdomain( 'asetec-odontologia', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Instancias (si las clases existen)
        if ( class_exists('ASETEC_ODO_Settings') )             ASETEC_ODO_Settings::instance();
        if ( class_exists('ASETEC_ODO_Shortcode_Reservar') )   ASETEC_ODO_Shortcode_Reservar::instance();
        if ( class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) ASETEC_ODO_Shortcode_Admin_Agenda::instance();
        if ( class_exists('ASETEC_ODO_Cron') )                 ASETEC_ODO_Cron::instance();
        // ASETEC_ODO_Dashboard y endpoints se auto-instancian en sus archivos
    }

    /** Activación / desactivación */
    public static function activate() {
        // Defaults de settings
        if ( class_exists('ASETEC_ODO_Settings') ) ASETEC_ODO_Settings::bootstrap_defaults();

        // Cron: cada 15 min (si no existe)
        if ( ! wp_next_scheduled( 'asetec_odo_cron_reminders' ) ) {
            // asegura que el schedule esté registrado
            add_filter( 'cron_schedules', ['ASETEC_Odontologia','_qhour'] );
            wp_schedule_event( time() + 60, 'quarterhourly', 'asetec_odo_cron_reminders' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'asetec_odo_cron_reminders' );
        if ( $ts ) wp_unschedule_event( $ts, 'quarterhourly', 'asetec_odo_cron_reminders' );
        flush_rewrite_rules();
    }

    /** Agrega el intervalo quarterhourly (15 min) */
    public static function _qhour( $schedules ) {
        if ( empty($schedules['quarterhourly']) ) {
            $schedules['quarterhourly'] = [
                'interval' => 15 * 60,
                'display'  => __( 'Cada 15 minutos', 'asetec-odontologia' ),
            ];
        }
        return $schedules;
    }
}

// Registrar el schedule SIEMPRE (una sola vez)
add_filter( 'cron_schedules', ['ASETEC_Odontologia','_qhour'] );

// Hooks de activación/desactivación
register_activation_hook( __FILE__, ['ASETEC_Odontologia','activate'] );
register_deactivation_hook( __FILE__, ['ASETEC_Odontologia','deactivate'] );

// Bootstrap
ASETEC_Odontologia::instance();
