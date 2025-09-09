<?php
/**
 * Plugin Name: ASETEC Odontología
 * Description: Gestión de citas odontológicas (slots editables, reservas, agenda admin, dashboard y correos .ics).
 * Version: 0.3.5
 * Author: ASETEC
 * Text Domain: asetec-odontologia
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class ASETEC_Odontologia {
    const VERSION = '0.3.7';
    private static $instance = null;

    /** Feature flags (puedes activar más adelante) */
    const ENABLE_PUBLIC  = true;   // shortcode público [odo_reservar]
    const ENABLE_AGENDA  = true;   // shortcode admin [odo_admin_agenda]
    const ENABLE_CRON    = true;   // recordatorios
    const ENABLE_DASH    = false;  // 🚫 dashboard/reportes desactivado por defecto

    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct() {
        $this->define_constants();

        // Cargar traducciones correctamente
        add_action( 'init', [ $this, 'load_textdomain' ] );

        // Incluir archivos en init (evita ejecuciones tempranas/fatales)
        add_action( 'init', [ $this, 'includes_safe' ], 5 );

        // Registrar CPT/estados cuando todo está incluido
        add_action( 'init', [ $this, 'register_types' ], 10 );

        // Instanciar servicios/shortcodes cuando todo está cargado
        add_action( 'init', [ $this, 'boot' ], 20 );

        // Cargar filtros/acciones dependientes de otros plugins si hace falta
        add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

        // Siempre registrar el nuevo schedule
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

    /** include seguro (nunca fatal si el archivo no existe) */
    private function safe_include( $rel ){
        $path = ASETEC_ODO_DIR . ltrim( $rel, '/' );
        if ( file_exists( $path ) ) { require_once $path; return true; }
        error_log( '[ASETEC_ODO] Falta include: ' . $rel );
        return false;
    }

    /** Incluye SOLO lo necesario, en el momento correcto */
    public function includes_safe(){
        // Helpers/base
        $this->safe_include('includes/helpers.php');
        $this->safe_include('includes/class-cpt.php');
        $this->safe_include('includes/class-states.php');
        $this->safe_include('includes/class-settings.php');
        $this->safe_include('includes/class-availability.php');
        


        // Emails (antes de endpoints que los usan)
        $this->safe_include('includes/class-emails.php');

        // Cron
        if ( self::ENABLE_CRON ) {
            $this->safe_include('includes/class-cron.php');
        }

        // Shortcodes
        if ( self::ENABLE_PUBLIC ) {
            $this->safe_include('includes/class-shortcode-reservar.php');
        }
        if ( self::ENABLE_AGENDA ) {
            $this->safe_include('includes/class-shortcode-admin-agenda.php');
            $this->safe_include('includes/class-admin-endpoints.php'); // AJAX admin
            $this->safe_include('includes/class-shortcode-admin-agenda-lite.php');
            $this->safe_include('includes/class-shortcode-admin-agenda-tui.php');
        }

        // Dashboard / reportes (opcional)
        if ( self::ENABLE_DASH ) {
            $this->safe_include('includes/class-dashboard.php');
            $this->safe_include('includes/class-shortcode-dashboard.php');
        }
    }

    /** Registrar CPT/Estados cuando ya están incluidas las clases */
    public function register_types(){
        if ( class_exists('ASETEC_ODO_CPT') )    ASETEC_ODO_CPT::register();
        if ( class_exists('ASETEC_ODO_States') ) ASETEC_ODO_States::register();
    }

    /** Instanciar servicios/shortcodes (ya con todo incluido) */
    public function boot(){
        if ( class_exists('ASETEC_ODO_Settings') )             ASETEC_ODO_Settings::instance();
        if ( self::ENABLE_PUBLIC && class_exists('ASETEC_ODO_Shortcode_Reservar') )
            ASETEC_ODO_Shortcode_Reservar::instance();

        if ( self::ENABLE_AGENDA && class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') )
            ASETEC_ODO_Shortcode_Admin_Agenda::instance();
        
        if ( self::ENABLE_AGENDA && class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_TUI') ) {
            ASETEC_ODO_Shortcode_Admin_Agenda_TUI::instance();
        }


        if ( self::ENABLE_CRON && class_exists('ASETEC_ODO_Cron') )
            ASETEC_ODO_Cron::instance();

        // Endpoints: si no se auto-instancian en su archivo, nos aseguramos aquí
        if ( self::ENABLE_AGENDA && class_exists('ASETEC_ODO_Admin_Endpoints') ) {
            if ( ! isset($GLOBALS['asetec_odo_admin_endpoints']) ) {
                $GLOBALS['asetec_odo_admin_endpoints'] = new ASETEC_ODO_Admin_Endpoints();
            }
        }

        // Dashboard (solo si está habilitado y existen las clases)
        if ( self::ENABLE_DASH && class_exists('ASETEC_ODO_Shortcode_Dashboard') ) {
            new ASETEC_ODO_Shortcode_Dashboard();
        }
    }

    public function plugins_loaded(){
        // Lugar para integraciones con otros plugins si hace falta
    }

    /** Activación / desactivación */
    public static function activate() {
        // Defaults de settings
        if ( class_exists('ASETEC_ODO_Settings') ) ASETEC_ODO_Settings::bootstrap_defaults();

        // Cron: cada 15 min (si no existe)
        if ( self::ENABLE_CRON && ! wp_next_scheduled( 'asetec_odo_cron_reminders' ) ) {
            // asegura que el schedule esté registrado
            add_filter( 'cron_schedules', [ __CLASS__, '_qhour' ] );
            wp_schedule_event( time() + 60, 'quarterhourly', 'asetec_odo_cron_reminders' );
        }
        flush_rewrite_rules();
    }

    public static function deactivate() {
        if ( self::ENABLE_CRON ) {
            $ts = wp_next_scheduled( 'asetec_odo_cron_reminders' );
            if ( $ts ) wp_unschedule_event( $ts, 'quarterhourly', 'asetec_odo_cron_reminders' );
        }
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

// Hooks de activación/desactivación
register_activation_hook( __FILE__, ['ASETEC_Odontologia','activate'] );
register_deactivation_hook( __FILE__, ['ASETEC_Odontologia','deactivate'] );

// Bootstrap
ASETEC_Odontologia::instance();
