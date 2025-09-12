(function($){
  let lineChart = null;
  let donutChart = null;

  function getStates(){
    const arr = [];
    $('.odo-states input.st:checked').each(function(){
      arr.push($(this).val());
    });
    return arr;
  }

  function paintKPIs(k){
    $('#k-total').text(k.total || 0);
    $('#k-pend').text(k.pendiente || 0);
    $('#k-aprob').text(k.aprobada || 0);
    $('#k-real').text(k.realizada || 0);
    $('#k-canu').text(k.cancelada_usuario || 0);
    $('#k-cana').text(k.cancelada_admin || 0);
    $('#k-reprog').text(k.reprogramada || 0);
  }

  function paintLine(labels, values){
    const ctx = document.getElementById('odo-chart-line').getContext('2d');
    if (lineChart) { lineChart.destroy(); }
    lineChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Citas',
          data: values,
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  function paintDonut(labels, values){
    const ctx = document.getElementById('odo-chart-donut').getContext('2d');
    if (donutChart) { donutChart.destroy(); }
    donutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: values
        }]
      },
      options: { responsive: true }
    });
  }

  function refresh(){
    const payload = {
      action: ASETEC_ODO_DASH.action,
      nonce: ASETEC_ODO_DASH.nonce,
      from: $('#odo-from').val(),
      to:   $('#odo-to').val(),
      q:    $('#odo-q').val(),
      states: getStates()
    };

    $.post(ASETEC_ODO_DASH.ajax, payload, function(res){
      if(!res || !res.success){ return; }
      paintKPIs(res.data.kpis || {});
      const l = res.data.line || {labels:[],values:[]};
      const d = res.data.donut|| {labels:[],values:[]};
      paintLine(l.labels, l.values);
      paintDonut(d.labels, d.values);
    }).fail(function(){
      // No rompemos la UI si hay fallo
    });
  }

  function exportCSV(){
    const qs = new URLSearchParams();
    qs.set('action', ASETEC_ODO_DASH.csv);
    qs.set('nonce', ASETEC_ODO_DASH.nonce);
    qs.set('from',  $('#odo-from').val());
    qs.set('to',    $('#odo-to').val());
    qs.set('q',     $('#odo-q').val());
    getStates().forEach(s => qs.append('states[]', s));
    window.location = ASETEC_ODO_DASH.ajax + '?' + qs.toString();
  }

  $(function(){
    $('#odo-refresh').on('click', refresh);
    $('#odo-export').on('click', exportCSV);
    refresh(); // carga inicial
  });

})(jQuery);
