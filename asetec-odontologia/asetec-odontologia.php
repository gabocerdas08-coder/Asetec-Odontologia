<?php
/**
 * Plugin Name: ASETEC Odontología
 * Description: Gestión de citas odontológicas (slots editables, reservas, agenda admin, dashboard y correos .ics).
 * Version: 0.3.12
 * Author: ASETEC
 * Text Domain: asetec-odontologia
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class ASETEC_Odontologia {
    const VERSION = '0.3.12';
    private static $instance = null;

    /** Feature flags */
    const ENABLE_PUBLIC  = true;
    const ENABLE_AGENDA  = true;
    const ENABLE_CRON    = true;
    const ENABLE_DASH    = true;

    /** Elige UNA variante de agenda: 'classic' | 'tui' | 'hybrid' */
    const ADMIN_AGENDA_VARIANT = 'hybrid';

    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct() {
        $this->define_constants();
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'includes_safe' ], 5 );
        add_action( 'init', [ $this, 'register_types' ], 10 );
        add_action( 'init', [ $this, 'boot' ], 20 );
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

        // Registrar el schedule una única vez aquí es suficiente
        add_filter( 'cron_schedules', [ __CLASS__, '_qhour' ] );
    }

    private function define_constants() {
        if ( ! defined('ASETEC_ODO_FILE') ) define( 'ASETEC_ODO_FILE', __FILE__ );
        if ( ! defined('ASETEC_ODO_DIR') )  define( 'ASETEC_ODO_DIR', plugin_dir_path( __FILE__ ) );
        if ( ! defined('ASETEC_ODO_URL') )  define( 'ASETEC_ODO_URL', plugin_dir_url( __FILE__ ) );
    }

    public function load_textdomain(){
        load_plugin_textdomain( 'asetec-odontologia', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    private function safe_include( $rel ){
        $path = ASETEC_ODO_DIR . ltrim( $rel, '/' );
        if ( file_exists( $path ) ) { require_once $path; return true; }
        error_log( '[ASETEC_ODO] Falta include: ' . $rel );
        return false;
    }

    public function includes_safe(){
        // Base
        $this->safe_include('includes/helpers.php');
        $this->safe_include('includes/class-cpt.php');
        $this->safe_include('includes/class-states.php');
        $this->safe_include('includes/class-settings.php');
        $this->safe_include('includes/class-availability.php');
        $this->safe_include('includes/class-emails.php');

        if ( self::ENABLE_CRON ) {
            $this->safe_include('includes/class-cron.php');
        }

        if ( self::ENABLE_PUBLIC ) {
            $this->safe_include('includes/class-shortcode-reservar.php');
        }

        if ( self::ENABLE_AGENDA ) {
            // Incluye SOLO la variante elegida
            switch ( self::ADMIN_AGENDA_VARIANT ) {
                case 'classic':
                    $this->safe_include('includes/class-shortcode-admin-agenda.php');
                    break;
                case 'tui':
                    $this->safe_include('includes/class-shortcode-admin-agenda-tui.php');
                    break;
                case 'hybrid':
                default:
                    $this->safe_include('includes/class-shortcode-admin-agenda-hybrid.php');
                    break;
            }
            $this->safe_include('includes/class-admin-endpoints.php');
        }

        if ( self::ENABLE_DASH ) {
            $this->safe_include('includes/class-dashboard.php');
            $this->safe_include('includes/class-shortcode-dashboard.php');
        }
    }

    public function register_types(){
        if ( class_exists('ASETEC_ODO_CPT') )    ASETEC_ODO_CPT::register();
        if ( class_exists('ASETEC_ODO_States') ) ASETEC_ODO_States::register();
    }

    public function boot(){
        if ( class_exists('ASETEC_ODO_Settings') ) ASETEC_ODO_Settings::instance();

        if ( self::ENABLE_PUBLIC && class_exists('ASETEC_ODO_Shortcode_Reservar') ) {
            ASETEC_ODO_Shortcode_Reservar::instance();
        }

        if ( self::ENABLE_AGENDA ) {
            // Instancia SOLO la variante elegida
            if ( self::ADMIN_AGENDA_VARIANT === 'classic' && class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {
                ASETEC_ODO_Shortcode_Admin_Agenda::instance();
            } elseif ( self::ADMIN_AGENDA_VARIANT === 'tui' && class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_TUI') ) {
                ASETEC_ODO_Shortcode_Admin_Agenda_TUI::instance();
            } elseif ( class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_Hybrid') ) {
                ASETEC_ODO_Shortcode_Admin_Agenda_Hybrid::instance();
            }

            if ( class_exists('ASETEC_ODO_Admin_Endpoints') && ! isset($GLOBALS['asetec_odo_admin_endpoints']) ) {
                $GLOBALS['asetec_odo_admin_endpoints'] = new ASETEC_ODO_Admin_Endpoints();
            }
        }

        if ( self::ENABLE_CRON && class_exists('ASETEC_ODO_Cron') )
            ASETEC_ODO_Cron::instance();

        if ( self::ENABLE_DASH && class_exists('ASETEC_ODO_Shortcode_Dashboard') )
            new ASETEC_ODO_Shortcode_Dashboard();
    }

    public function plugins_loaded(){}

    public static function activate() {
        if ( class_exists('ASETEC_ODO_Settings') ) ASETEC_ODO_Settings::bootstrap_defaults();

        if ( self::ENABLE_CRON && ! wp_next_scheduled( 'asetec_odo_cron_reminders' ) ) {
            wp_schedule_event( time() + 60, 'quarterhourly', 'asetec_odo_cron_reminders' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        if ( self::ENABLE_CRON ) {
            if ( $ts = wp_next_scheduled( 'asetec_odo_cron_reminders' ) ) {
                // CORRECTO: 2º parámetro es el HOOK
                wp_unschedule_event( $ts, 'asetec_odo_cron_reminders' );
            }
        }
        flush_rewrite_rules();
    }

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

// Hooks de activación/desactivación
register_activation_hook( __FILE__, ['ASETEC_Odontologia','activate'] );
register_deactivation_hook( __FILE__, ['ASETEC_Odontologia','deactivate'] );

// Bootstrap (¡asegúrate de que ESTA línea exista!)
ASETEC_Odontologia::instance();
