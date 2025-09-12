(function($){
  let lineChart = null;
  let donutChart = null;

  // --- Helpers ---
  function getStates(){
    const arr = [];
    $('.odo-states input.st:checked').each(function(){
      arr.push($(this).val());
    });
    return arr;
  }

  function safeSet(id, val){
    const el = document.getElementById(id);
    if (el) el.textContent = (val ?? 0);
  }

  function paintKPIs(k){
    safeSet('k-total',   k?.total);
    safeSet('k-pend',    k?.pendiente);
    safeSet('k-aprob',   k?.aprobada);
    safeSet('k-real',    k?.realizada);
    safeSet('k-canu',    k?.cancelada_usuario);
    safeSet('k-cana',    k?.cancelada_admin);
    safeSet('k-reprog',  k?.reprogramada);
    // Puedes agregar más KPIs aquí si lo necesitas
  }

  function paintLine(labels, values){
    const canvas = document.getElementById('odo-chart-line');
    if (!canvas || typeof Chart === 'undefined') return;
    const ctx = canvas.getContext('2d');
    if (lineChart) { try { lineChart.destroy(); } catch(e){} }
    lineChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels || [],
        datasets: [{
          label: 'Citas',
          data: values || [],
          tension: 0.3,
          borderColor: '#3b82f6',
          backgroundColor: 'rgba(59,130,246,0.1)',
          pointBackgroundColor: '#3b82f6'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  function paintDonut(labels, values){
    const canvas = document.getElementById('odo-chart-donut');
    if (!canvas || typeof Chart === 'undefined') return;
    const ctx = canvas.getContext('2d');
    if (donutChart) { try { donutChart.destroy(); } catch(e){} }
    donutChart = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels || [],
        datasets: [{
          data: values || [],
          backgroundColor: [
            '#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#64748b'
          ]
        }]
      },
      options: { responsive: true, maintainAspectRatio: false }
    });
  }

  function refresh(){
    if (!window.ASETEC_ODO_DASH) return;

    const payload = {
      action: ASETEC_ODO_DASH.action,
      nonce:  ASETEC_ODO_DASH.nonce,
      from:   $('#odo-from').val(),
      to:     $('#odo-to').val(),
      q:      $('#odo-q').val(),
      states: getStates()
    };

    $.post(ASETEC_ODO_DASH.ajax, payload, function(res){
      if (!res || !res.success || !res.data) return;

      // KPIs
      paintKPIs(res.data.kpis || {});

      // Gráfica de línea
      const l = res.data.line || {labels:[], values:[]};
      paintLine(l.labels, l.values);

      // Donut
      const d = res.data.donut || {labels:[], values:[]};
      paintDonut(d.labels, d.values);
    }).fail(function(){
      // Puedes mostrar un toast o mensaje de error aquí si lo deseas
    });
  }

  function exportCSV(){
    if (!window.ASETEC_ODO_DASH) return;
    const qs = new URLSearchParams();
    qs.set('action', ASETEC_ODO_DASH.csv);
    qs.set('nonce',  ASETEC_ODO_DASH.nonce);
    qs.set('from',   $('#odo-from').val());
    qs.set('to',     $('#odo-to').val());
    qs.set('q',      $('#odo-q').val());
    getStates().forEach(s => qs.append('states[]', s));
    window.location = ASETEC_ODO_DASH.ajax + '?' + qs.toString();
  }

  $(function(){
    $('#odo-refresh').on('click', refresh);
    $('#odo-export').on('click', exportCSV);

    // Primera carga automática
    refresh();
  });

})(jQuery);
