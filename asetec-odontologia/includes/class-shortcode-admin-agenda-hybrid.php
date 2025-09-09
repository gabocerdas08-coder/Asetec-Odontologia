<?php
if ( ! defined('ABSPATH') ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda_Hybrid') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda_Hybrid {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode( 'odo_admin_agenda3', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
    }

    public function assets(){
        // FullCalendar (locales que ya tenías)
        wp_register_style( 'fc-core', ASETEC_ODO_URL.'assets/fullcalendar/fullcalendar.min.css', [], '6.1.0' );
        wp_register_script('fc-core', ASETEC_ODO_URL.'assets/fullcalendar/fullcalendar.min.js',  [], '6.1.0', true );

        // CSS de la agenda (estética)
        wp_register_style( 'asetec-odo-admin-agenda', ASETEC_ODO_URL.'assets/css/admin-agenda.css', [], ASETEC_Odontologia::VERSION );

        // JS híbrido
        wp_register_script(
            'asetec-odo-admin-hybrid',
            ASETEC_ODO_URL.'assets/js/admin-agenda-hybrid.js',
            [ 'jquery', 'fc-core' ],
            ASETEC_Odontologia::VERSION,
            true
        );

        wp_localize_script('asetec-odo-admin-hybrid', 'ASETEC_ODO_ADMIN3', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('asetec_odo_admin'),
            'labels'=> [
                'search'    => __('Buscar (nombre o cédula)…', 'asetec-odontologia'),
                'new'       => __('Nueva cita', 'asetec-odontologia'),
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
                'status'    => __('Estado', 'asetec-odontologia'),
            ],
        ]);
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<p>'.esc_html__('No autorizado.', 'asetec-odontologia').'</p>';
        }

        wp_enqueue_style('fc-core');
        wp_enqueue_style('asetec-odo-admin-agenda');
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin-hybrid');

        ob_start(); ?>
        <div class="odo3-wrap">
            <h2 class="odo3-title"><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>

            <div class="odo3-toolbar">
                <div class="odo3-legend">
                    <span><i class="odo3-dot" style="background:#f59e0b"></i> <?php esc_html_e('Pendiente','asetec-odontologia'); ?></span>
                    <span><i class="odo3-dot" style="background:#3b82f6"></i> <?php esc_html_e('Aprobada','asetec-odontologia'); ?></span>
                    <span><i class="odo3-dot" style="background:#10b981"></i> <?php esc_html_e('Realizada','asetec-odontologia'); ?></span>
                    <span><i class="odo3-dot" style="background:#ef4444"></i> <?php esc_html_e('Cancelada','asetec-odontologia'); ?></span>
                    <span><i class="odo3-dot" style="background:#8b5cf6"></i> <?php esc_html_e('Reprogramada','asetec-odontologia'); ?></span>
                </div>

                <div class="odo3-actions">
                    <div class="odo3-viewgroup" role="group" aria-label="<?php esc_attr_e('Cambiar vista','asetec-odontologia'); ?>">
                        <button class="odo3-view" data-view="dayGridMonth">Mes</button>
                        <button class="odo3-view is-active" data-view="timeGridWeek">Semana</button>
                        <button class="odo3-view" data-view="timeGridDay">Día</button>
                    </div>
                    <input id="odo3-search" class="odo3-search" type="search" placeholder="<?php echo esc_attr__('Buscar (nombre o cédula)…','asetec-odontologia'); ?>">
                    <button id="odo3-new" class="odo3-btn-primary"><?php esc_html_e('Nueva cita','asetec-odontologia'); ?></button>
                </div>
            </div>

            <div class="odo3-filters">
                <?php
                $estados = [
                    'pendiente'         => __('pendiente','asetec-odontologia'),
                    'aprobada'          => __('aprobada','asetec-odontologia'),
                    'realizada'         => __('realizada','asetec-odontologia'),
                    'cancelada_usuario' => __('cancelada usuario','asetec-odontologia'),
                    'cancelada_admin'   => __('cancelada admin','asetec-odontologia'),
                    'reprogramada'      => __('reprogramada','asetec-odontologia'),
                ];
                foreach($estados as $val=>$label){
                    echo '<label class="odo3-chip">';
                    echo '<input class="odo3-filter" type="checkbox" value="'.esc_attr($val).'" checked> '.esc_html($label);
                    echo '</label>';
                }
                ?>
            </div>

            <div id="odo3-calendar" class="odo3-calendar"></div>

            <!-- Modal -->
            <div id="odo3-modal" class="odo3-modal" aria-hidden="true">
                <div class="odo3-backdrop"></div>
                <div class="odo3-dialog" role="dialog" aria-modal="true" aria-labelledby="odo3-modal-title">
                    <div class="odo3-dialog-head">
                        <strong id="odo3-modal-title">Cita</strong>
                        <button id="odo3-close" class="odo3-btn"><?php esc_html_e('Cerrar','asetec-odontologia'); ?></button>
                    </div>
                    <div class="odo3-dialog-body">
                        <div class="odo3-grid">
                            <div class="odo3-field"><label><?php esc_html_e('Inicio','asetec-odontologia'); ?></label><input type="datetime-local" id="odo3-start"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Fin','asetec-odontologia'); ?></label><input type="datetime-local" id="odo3-end"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Nombre completo','asetec-odontologia'); ?></label><input id="odo3-nombre" type="text"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Cédula','asetec-odontologia'); ?></label><input id="odo3-cedula" type="text"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Correo','asetec-odontologia'); ?></label><input id="odo3-correo" type="email"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Teléfono','asetec-odontologia'); ?></label><input id="odo3-telefono" type="tel"></div>
                            <div class="odo3-field"><label><?php esc_html_e('Estado','asetec-odontologia'); ?></label>
                                <select id="odo3-estado">
                                    <option value="pendiente"><?php esc_html_e('pendiente','asetec-odontologia'); ?></option>
                                    <option value="aprobada"><?php esc_html_e('aprobada','asetec-odontologia'); ?></option>
                                    <option value="realizada"><?php esc_html_e('realizada','asetec-odontologia'); ?></option>
                                    <option value="cancelada_usuario"><?php esc_html_e('cancelada usuario','asetec-odontologia'); ?></option>
                                    <option value="cancelada_admin"><?php esc_html_e('cancelada admin','asetec-odontologia'); ?></option>
                                    <option value="reprogramada"><?php esc_html_e('reprogramada','asetec-odontologia'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="odo3-dialog-foot">
                        <div class="odo3-actionsL">
                            <button id="odo3-approve" class="odo3-btn-primary"><?php esc_html_e('Aprobar','asetec-odontologia'); ?></button>
                            <button id="odo3-done" class="odo3-btn-green"><?php esc_html_e('Realizada','asetec-odontologia'); ?></button>
                            <button id="odo3-cancel" class="odo3-btn-danger"><?php esc_html_e('Cancelar','asetec-odontologia'); ?></button>
                        </div>
                        <div class="odo3-actionsR">
                            <button id="odo3-save" class="odo3-btn-primary"><?php esc_html_e('Guardar','asetec-odontologia'); ?></button>
                            <button id="odo3-update" class="odo3-btn"><?php esc_html_e('Actualizar','asetec-odontologia'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="odo3-toast" class="odo3-toast"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

}
