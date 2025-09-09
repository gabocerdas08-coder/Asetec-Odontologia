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
    // 1) Pin de versión estable (evita sorpresas con "latest")
    $ver = '2.1.3';

    // 2) CDN primario (jsDelivr) + secundario (TOAST UI CDN)
    $tui_css_primary = "https://cdn.jsdelivr.net/npm/@toast-ui/calendar@{$ver}/dist/toastui-calendar.min.css";
    $tui_js_primary  = "https://cdn.jsdelivr.net/npm/@toast-ui/calendar@{$ver}/dist/toastui-calendar.min.js";

    $tui_css_backup  = "https://uicdn.toast.com/calendar/v{$ver}/toastui-calendar.min.css";
    $tui_js_backup   = "https://uicdn.toast.com/calendar/v{$ver}/toastui-calendar.min.js";

    // 3) (Opcional) rutas locales — súbelas si tu hosting bloquea CDNs
    //    assets/vendor/tui/toastui-calendar.min.css y .js
    $tui_css_local = ASETEC_ODO_URL . 'assets/vendor/tui/toastui-calendar.min.css';
    $tui_js_local  = ASETEC_ODO_URL . 'assets/vendor/tui/toastui-calendar.min.js';

    // Registramos el primario
    wp_register_style('tui-calendar',  $tui_css_primary, [], $ver);
    wp_register_script('tui-calendar', $tui_js_primary,  [], $ver, true);

    // Encolamos un pequeño inline que hace fallback si falla el primario
    $fallback = "
    (function(){
      function have(){return (window.tui && window.tui.Calendar) || (window.toastui && window.toastui.Calendar);}
      function load(src, cb){
        var s=document.createElement('script'); s.src=src; s.async=true; s.onload=cb; s.onerror=cb; document.head.appendChild(s);
      }
      function loadCss(href){
        if ([].some.call(document.styleSheets,function(ss){return (ss.href||'').indexOf(href)>-1;})) return;
        var l=document.createElement('link'); l.rel='stylesheet'; l.href=href; document.head.appendChild(l);
      }
      function tryBackup(){
        if (have()) return;
        loadCss('{$tui_css_backup}');
        load('{$tui_js_backup}', function(){
          if (have()) return;
          // último intento: local
          loadCss('{$tui_css_local}');
          load('{$tui_js_local}', function(){});
        });
      }
      // Si en ~400ms no existe el global, disparamos backup
      setTimeout(function(){ if(!have()) tryBackup(); }, 400);
    })();
    ";
    wp_add_inline_script('tui-calendar', $fallback);

    // Nuestro Web Component DEPENDE de TUI
    wp_register_script(
        'asetec-odo-admin-tui',
        ASETEC_ODO_URL . 'assets/js/admin-agenda-tui.js',
        [ 'tui-calendar' ],
        ASETEC_Odontologia::VERSION,
        true
    );

    wp_localize_script('asetec-odo-admin-tui', 'ASETEC_ODO_ADMIN2', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('asetec_odo_admin'),
        'labels'=> [ /* tus labels */ ]
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
