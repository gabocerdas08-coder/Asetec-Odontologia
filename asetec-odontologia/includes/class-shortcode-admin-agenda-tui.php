<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_TUI') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda_TUI {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda3', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets(){
        // TUI Calendar por CDN (JS + CSS) — se inyectan también desde el Web Component por seguridad,
        // pero registramos aquí por si el sitio bloquea cargas dinámicas.
        wp_register_style(
            'tui-calendar',
            'https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css',
            [],
            'latest'
        );
        wp_register_script(
            'tui-calendar',
            'https://uicdn.toast.com/calendar/latest/toastui-calendar.min.js',
            [],
            'latest',
            true
        );

        // Nuestro Web Component (no depende de jQuery)
        wp_register_script(
            'asetec-odo-admin-tui',
            ASETEC_ODO_URL . 'assets/js/admin-agenda-tui.js',
            [],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script('asetec-odo-admin-tui', 'ASETEC_ODO_ADMIN2', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'labels'=> [
                'title'     => __('Agenda Odontología', 'asetec-odontologia'),
                'new'       => __('Nueva cita', 'asetec-odontologia'),
                'search'    => __('Buscar (nombre o cédula)…', 'asetec-odontologia'),
                'save'      => __('Guardar', 'asetec-odontologia'),
                'update'    => __('Actualizar', 'asetec-odontologia'),
                'approve'   => __('Aprobar', 'asetec-odontologia'),
                'cancel'    => __('Cancelar', 'asetec-odontologia'),
                'done'      => __('Realizada', 'asetec-odontologia'),
                'close'     => __('Cerrar', 'asetec-odontologia'),
                'start'     => __('Inicio', 'asetec-odontologia'),
                'end'       => __('Fin', 'asetec-odontologia'),
                'duration'  => __('Duración (min)', 'asetec-odontologia'),
                'name'      => __('Nombre completo', 'asetec-odontologia'),
                'id'        => __('Cédula', 'asetec-odontologia'),
                'email'     => __('Correo', 'asetec-odontologia'),
                'phone'     => __('Teléfono', 'asetec-odontologia'),
                'status'    => __('Estado', 'asetec-odontologia')
            ]
        ]);
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<p>' . esc_html__('No autorizado.', 'asetec-odontologia') . '</p>';
        }

        // Encolamos: si la página bloquea carga dinámica, esto asegura disponibilidad.
        wp_enqueue_style('tui-calendar');
        wp_enqueue_script('tui-calendar');
        wp_enqueue_script('asetec-odo-admin-tui');

        ob_start(); ?>
        <div class="wrap modulo-asetec">
            <h2><?php echo esc_html__('Agenda Odontología', 'asetec-odontologia'); ?></h2>
            <!-- Web Component aislado en Shadow DOM -->
            <asetec-odo-agenda></asetec-odo-agenda>
        </div>
        <?php
        return ob_get_clean();
    }
}

}
