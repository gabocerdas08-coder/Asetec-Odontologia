<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ASETEC_ODO_Shortcode_Dashboard {
    private static $instance = null;
    public static function instance(){ return self::$instance ?? ( self::$instance = new self() ); }

    private function __construct(){
        add_shortcode('odo_dashboard',[ $this,'render' ]);
        add_action('wp_enqueue_scripts',[ $this,'assets' ]);
    }

    public function assets(){
        wp_register_script('chartjs', ASETEC_ODO_URL.'assets/vendor/chartjs/chart.umd.js', [], ASETEC_Odontologia::VERSION, true);
        wp_register_script('odo-dashboard', ASETEC_ODO_URL.'assets/js/dashboard.js', [ 'jquery','chartjs' ], ASETEC_Odontologia::VERSION, true);
        wp_localize_script('odo-dashboard','ASETEC_ODO_DASH', [
            'ajax'=>admin_url('admin-ajax.php'),
            'nonce'=>wp_create_nonce('asetec_odo_admin')
        ]);
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) return '<p>No autorizado.</p>';
        wp_enqueue_script('chartjs');
        wp_enqueue_script('odo-dashboard');
        ob_start(); ?>
        <div class="wrap">
          <h2><?php esc_html_e('Dashboard de Citas', 'asetec-odontologia'); ?></h2>
          <div style="display:flex;gap:12px;align-items:end;margin:10px 0;flex-wrap:wrap">
            <label>Desde <input type="datetime-local" id="odo_from" value="<?php echo esc_attr(date('Y-m-01\T00:00')); ?>"></label>
            <label>Hasta <input type="datetime-local" id="odo_to" value="<?php echo esc_attr(date('Y-m-t\T23:59')); ?>"></label>
            <button class="button" id="odo_load">Actualizar</button>
            <a class="button button-secondary" id="odo_csv" target="_blank"><?php esc_html_e('Exportar CSV','asetec-odontologia'); ?></a>
          </div>
          <canvas id="odo_chart_bar" height="120"></canvas>
          <canvas id="odo_chart_pie" height="120" style="margin-top:16px"></canvas>
        </div>
        <?php return ob_get_clean();
    }
}
ASETEC_ODO_Shortcode_Dashboard::instance();
```

**`assets/js/dashboard.js`**
```javascript
(function($){
  function load(){
    var f = $('#odo_from').val().replace('T',' ') + ':00';
    var t = $('#odo_to').val().replace('T',' ') + ':59';
    $.post(ASETEC_ODO_DASH.ajax, { action:'asetec_odo_stats', nonce:ASETEC_ODO_DASH.nonce, from:f, to:t }, function(res){
      if(!res.success){ alert(res.data && res.data.msg || 'Error'); return; }
      var d = res.data.data;
      var labels = Object.keys(d);
      var values = labels.map(function(k){ return d[k]; });
      var ctx1 = document.getElementById('odo_chart_bar').getContext('2d');
      if(window.__odo_bar) window.__odo_bar.destroy();
      window.__odo_bar = new Chart(ctx1, { type:'bar', data:{ labels:labels, datasets:[{ label:'Citas', data:values }] } });
      var ctx2 = document.getElementById('odo_chart_pie').getContext('2d');
      if(window.__odo_pie) window.__odo_pie.destroy();
      window.__odo_pie = new Chart(ctx2, { type:'pie', data:{ labels:labels, datasets:[{ data:values }] } });
      $('#odo_csv').attr('href', ASETEC_ODO_DASH.ajax + '?action=asetec_odo_export_csv&nonce='+ASETEC_ODO_DASH.nonce+'&from='+encodeURIComponent(f)+'&to='+encodeURIComponent(t));
    });
  }
  $(function(){ $('#odo_load').on('click', load); load(); });
})(jQuery);