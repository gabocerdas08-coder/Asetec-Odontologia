(function($){
  // Mini helper
  function valStates(){
    var s=[]; $('.odo-states .st:checked').each(function(){ s.push(this.value); }); return s;
  }
  function getFilters(){
    return {
      from: $('#odo-from').val(),
      to:   $('#odo-to').val(),
      q:    $('#odo-q').val(),
      states: valStates()
    };
  }

  // Charts
  let lineChart=null, donutChart=null;

  function renderCharts(data){
    if (typeof window.Chart === 'undefined') {
      console.error('Chart.js no se cargó');
      return;
    }
    // KPIs
    $('#k-total').text(data.kpis.total);
    $('#k-pend').text(data.kpis.pendiente);
    $('#k-aprob').text(data.kpis.aprobada);
    $('#k-real').text(data.kpis.realizada);
    $('#k-canu').text(data.kpis.cancelada_usuario);
    $('#k-cana').text(data.kpis.cancelada_admin);
    $('#k-reprog').text(data.kpis.reprogramada);

    // Line
    var ctx1 = document.getElementById('odo-chart-line').getContext('2d');
    if (lineChart) lineChart.destroy();
    lineChart = new Chart(ctx1, {
      type: 'line',
      data: {
        labels: data.line.labels,
        datasets: [{label:'Citas por día', data: data.line.values, tension:.25}]
      },
      options: {responsive:true, plugins:{legend:{display:false}}}
    });

    // Donut
    var ctx2 = document.getElementById('odo-chart-donut').getContext('2d');
    if (donutChart) donutChart.destroy();
    donutChart = new Chart(ctx2, {
      type: 'doughnut',
      data: { labels: data.donut.labels, datasets: [{ data: data.donut.values }] },
      options: {responsive:true}
    });
  }

  function loadData(){
    const payload = Object.assign({ action:'asetec_odo_dash_data', nonce:ASETEC_ODO_DASH.nonce }, getFilters());
    $('body').css('cursor','wait');
    $.post(ASETEC_ODO_DASH.ajax, payload).done(function(res){
      if(res && res.success){ renderCharts(res.data); }
      else { alert(ASETEC_ODO_DASH.i18n.error); }
    }).fail(function(){
      alert(ASETEC_ODO_DASH.i18n.error);
    }).always(function(){
      $('body').css('cursor','default');
    });
  }

  function exportCSV(){
    const f=getFilters();
    const url = ASETEC_ODO_DASH.ajax + '?' + $.param({
      action:'asetec_odo_dash_csv',
      nonce:ASETEC_ODO_DASH.nonce,
      from:f.from, to:f.to, q:f.q
    }) + f.states.map(s=>'&states[]='+encodeURIComponent(s)).join('');
    window.location = url;
  }

  // Default rango: últimos 7 días
  function setDefaultDates(){
    const today = new Date();
    const to = today.toISOString().slice(0,10);
    const fromD = new Date(today.getTime()-6*24*3600*1000);
    const from = fromD.toISOString().slice(0,10);
    $('#odo-from').val(from);
    $('#odo-to').val(to);
  }

  $(function(){
    if (!$('.odo-dash').length) return;
    setDefaultDates();
    $('#odo-refresh').on('click', loadData);
    $('#odo-export').on('click', exportCSV);
    // refresco al cambiar estados rápidamente
    $('.odo-states').on('change','input', function(){ loadData(); });
    loadData();
  });

})(jQuery);
