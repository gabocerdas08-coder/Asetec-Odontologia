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
