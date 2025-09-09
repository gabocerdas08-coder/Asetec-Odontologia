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
        // Usa tus assets locales de FullCalendar (ya los tienes en /assets/fullcalendar/)
        wp_register_style( 'fc-core', ASETEC_ODO_URL.'assets/fullcalendar/fullcalendar.min.css', [], '6.1.0' );
        wp_register_script('fc-core', ASETEC_ODO_URL.'assets/fullcalendar/fullcalendar.min.js',  [], '6.1.0', true );

        // Nuestro JS híbrido (depende de FullCalendar)
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
        wp_enqueue_script('fc-core');
        wp_enqueue_script('asetec-odo-admin-hybrid');

        ob_start(); ?>
        <div class="wrap modulo-asetec">
            <h2><?php esc_html_e('Agenda Odontología', 'asetec-odontologia'); ?></h2>

            <div class="odo3-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0 12px">
                <div class="legend" style="display:flex;gap:10px;font-size:12px;color:#374151;flex-wrap:wrap">
                    <span><i style="width:10px;height:10px;border-radius:999px;background:#f59e0b;display:inline-block;margin-right:6px"></i> <?php esc_html_e('Pendiente','asetec-odontologia'); ?></span>
                    <span><i style="width:10px;height:10px;border-radius:999px;background:#3b82f6;display:inline-block;margin-right:6px"></i> <?php esc_html_e('Aprobada','asetec-odontologia'); ?></span>
                    <span><i style="width:10px;height:10px;border-radius:999px;background:#10b981;display:inline-block;margin-right:6px"></i> <?php esc_html_e('Realizada','asetec-odontologia'); ?></span>
                    <span><i style="width:10px;height:10px;border-radius:999px;background:#ef4444;display:inline-block;margin-right:6px"></i> <?php esc_html_e('Cancelada','asetec-odontologia'); ?></span>
                    <span><i style="width:10px;height:10px;border-radius:999px;background:#8b5cf6;display:inline-block;margin-right:6px"></i> <?php esc_html_e('Reprogramada','asetec-odontologia'); ?></span>
                </div>

                <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <input id="odo3-search" type="search" placeholder="<?php echo esc_attr__('Buscar (nombre o cédula)…','asetec-odontologia'); ?>" style="min-width:240px;border:1px solid #d1d5db;border-radius:10px;padding:8px 10px">
                    <button id="odo3-new" class="button button-primary"><?php esc_html_e('Nueva cita','asetec-odontologia'); ?></button>
                </div>
            </div>

            <div class="odo3-filters" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
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
                    echo '<label style="display:inline-flex;gap:6px;align-items:center;background:#f3f4f6;padding:6px 8px;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer">';
                    echo '<input class="odo3-filter" type="checkbox" value="'.esc_attr($val).'" checked> '.esc_html($label);
                    echo '</label>';
                }
                ?>
            </div>

            <div id="odo3-calendar" style="min-height:640px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden"></div>

            <!-- Modal -->
            <div id="odo3-modal" style="position:fixed;inset:0;z-index:99999;display:none">
                <div class="backdrop" style="position:absolute;inset:0;background:rgba(15,23,42,.45)"></div>
                <div class="dialog" style="position:relative;max-width:760px;margin:6vh auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden">
                    <div class="header" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e5e7eb">
                        <strong id="odo3-modal-title">Cita</strong>
                        <button id="odo3-close" class="button"><?php esc_html_e('Cerrar','asetec-odontologia'); ?></button>
                    </div>
                    <div class="body" style="padding:16px 18px">
                        <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label><?php esc_html_e('Inicio','asetec-odontologia'); ?></label><input type="datetime-local" id="odo3-start"></div>
                            <div class="field"><label><?php esc_html_e('Fin','asetec-odontologia'); ?></label><input type="datetime-local" id="odo3-end"></div>
                            <div class="field"><label><?php esc_html_e('Nombre completo','asetec-odontologia'); ?></label><input id="odo3-nombre" type="text"></div>
                            <div class="field"><label><?php esc_html_e('Cédula','asetec-odontologia'); ?></label><input id="odo3-cedula" type="text"></div>
                            <div class="field"><label><?php esc_html_e('Correo','asetec-odontologia'); ?></label><input id="odo3-correo" type="email"></div>
                            <div class="field"><label><?php esc_html_e('Teléfono','asetec-odontologia'); ?></label><input id="odo3-telefono" type="tel"></div>
                            <div class="field"><label><?php esc_html_e('Estado','asetec-odontologia'); ?></label>
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
                    <div class="footer" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-top:1px solid #e5e7eb;background:#fafafa">
                        <div class="actionsL" style="display:flex;gap:8px;flex-wrap:wrap">
                            <button id="odo3-approve" class="button button-primary"><?php esc_html_e('Aprobar','asetec-odontologia'); ?></button>
                            <button id="odo3-done" class="button"><?php esc_html_e('Realizada','asetec-odontologia'); ?></button>
                            <button id="odo3-cancel" class="button button-danger" style="background:#b91c1c;color:#fff;border-color:#b91c1c"><?php esc_html_e('Cancelar','asetec-odontologia'); ?></button>
                        </div>
                        <div class="actionsR" style="display:flex;gap:8px;flex-wrap:wrap">
                            <button id="odo3-save" class="button button-primary"><?php esc_html_e('Guardar','asetec-odontologia'); ?></button>
                            <button id="odo3-update" class="button"><?php esc_html_e('Actualizar','asetec-odontologia'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="odo3-toast" style="position:fixed;right:14px;bottom:14px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;font-size:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(10px);transition:all .2s;z-index:999999"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

}
