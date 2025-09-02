<?php
/**
 * Plugin Name: ASETEC Odontología
 * Description: Gestión de citas odontológicas con horarios/slots editables, reservas con bloqueo inmediato y vistas de público/admin.
 * Version: 0.1.0
 * Author: ASETEC
 * Text Domain: asetec-odontologia
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class ASETEC_Odontologia {
    const VERSION = '0.1.0';
    private static $instance = null;

    public static function instance() {
        return self::$instance ?? ( self::$instance = new self() );
    }

    private function __construct() {
        $this->define_constants();
        $this->includes();
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
        register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );
        register_deactivation_hook( __FILE__, [ __CLASS__, 'deactivate' ] );
    }

    private function define_constants() {
        define( 'ASETEC_ODO_FILE', __FILE__ );
        define( 'ASETEC_ODO_DIR', plugin_dir_path( __FILE__ ) );
        define( 'ASETEC_ODO_URL', plugin_dir_url( __FILE__ ) );
    }

    private function includes() {
        require_once ASETEC_ODO_DIR . 'includes/helpers.php';
        require_once ASETEC_ODO_DIR . 'includes/class-cpt.php';
        require_once ASETEC_ODO_DIR . 'includes/class-states.php';
        require_once ASETEC_ODO_DIR . 'includes/class-settings.php';
        require_once ASETEC_ODO_DIR . 'includes/class-availability.php';
        require_once ASETEC_ODO_DIR . 'includes/class-shortcode-reservar.php';
        require_once ASETEC_ODO_DIR . 'includes/class-shortcode-admin-agenda.php';
        require_once ASETEC_ODO_DIR . 'includes/class-emails.php';
        require_once ASETEC_ODO_DIR . 'includes/class-cron.php';
    }

    public function init() {
        ASETEC_ODO_CPT::register();
        ASETEC_ODO_States::register();
    }

    public function plugins_loaded() {
        load_plugin_textdomain( 'asetec-odontologia', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
        ASETEC_ODO_Settings::instance();
        ASETEC_ODO_Shortcode_Reservar::instance();
        ASETEC_ODO_Shortcode_Admin_Agenda::instance();
        ASETEC_ODO_Cron::instance();
    }

    public static function activate() {
        ASETEC_ODO_Settings::bootstrap_defaults();
        if ( ! wp_next_scheduled( 'asetec_odo_cron_reminders' ) ) {
            wp_schedule_event( time() + 60, 'quarterhourly', 'asetec_odo_cron_reminders' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        $ts = wp_next_scheduled( 'asetec_odo_cron_reminders' );
        if ( $ts ) wp_unschedule_event( $ts, 'asetec_odo_cron_reminders' );
        flush_rewrite_rules();
    }
}

// Intervalo cada 15 min
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['quarterhourly'] ) ) {
        $schedules['quarterhourly'] = [
            'interval' => 15 * 60,
            'display'  => __( 'Cada 15 minutos', 'asetec-odontologia' ),
        ];
    }
    return $schedules;
});

ASETEC_Odontologia::instance();
