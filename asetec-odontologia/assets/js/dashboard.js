/* assets/js/dashboard.js */
(function($){
  let lineChart = null;
  let donutChart = null;
  let monthlyChart = null;

  // Helpers
  function getStates(){
    const arr = [];
    $('.odo-states input.st:checked').each(function(){ arr.push($(this).val()); });
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
  }

  function ensureCtx(id){
    const c = document.getElementById(id);
    if (!c) return null;
    // Si algún lazy/optimizador lo rompió, intenta recrear
    if (c.tagName.toLowerCase() !== 'canvas'){
      const box = c.closest('.odo-chart-box');
      if (box){
        box.innerHTML = '<canvas id="'+id+'" class="odo-chart" width="600" height="320"></canvas>';
        return document.getElementById(id).getContext('2d');
      }
      return null;
    }
    return c.getContext('2d');
  }

  function initLine(labels, values){
    if (typeof Chart === 'undefined') return; // Chart.js aún no
    const ctx = ensureCtx('odo-chart-line'); if (!ctx) return;
    if (!lineChart){
      lineChart = new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: [{ label:'Citas', data: values, tension:0.3 }] },
        options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
      });
    } else {
      lineChart.data.labels = labels;
      lineChart.data.datasets[0].data = values;
      lineChart.update();
    }
  }

  function initDonut(labels, values){
    if (typeof Chart === 'undefined') return;
    const ctx = ensureCtx('odo-chart-donut'); if (!ctx) return;
    if (!donutChart){
      donutChart = new Chart(ctx, {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: values }] },
        options: { responsive:true, maintainAspectRatio:false }
      });
    } else {
      donutChart.data.labels = labels;
      donutChart.data.datasets[0].data = values;
      donutChart.update();
    }
  }

  function initMonthly(labels, values){
    if (typeof Chart === 'undefined') return;
    const ctx = ensureCtx('odo-chart-monthly'); if (!ctx) return;
    if (!monthlyChart){
      monthlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: 'rgba(59,130,246,0.3)',
            borderColor: 'rgba(59,130,246,1)',
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { y: { beginAtZero: true } }
        }
      });
    } else {
      monthlyChart.data.labels = labels;
      monthlyChart.data.datasets[0].data = values;
      monthlyChart.update();
    }
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

      // Línea
      const L = res.data.line || {};
      const lLabels = Array.isArray(L.labels) ? L.labels : [];
      const lValues = Array.isArray(L.values) ? L.values : [];
      if (lLabels.length || lValues.length){
        initLine(lLabels, lValues);
      } else {
        // no borres el gráfico si ya existe
        if (!lineChart) initLine([], []);
      }

      // Donut
      const D = res.data.donut || {};
      const dLabels = Array.isArray(D.labels) ? D.labels : [];
      const dValues = Array.isArray(D.values) ? D.values : [];
      if (dLabels.length || dValues.length){
        initDonut(dLabels, dValues);
      } else {
        if (!donutChart) initDonut([], []);
      }

      // Mensual
      const M = res.data.monthly || {};
      const mLabels = Array.isArray(M.labels) ? M.labels : [];
      const mValues = Array.isArray(M.values) ? M.values : [];
      if (mLabels.length || mValues.length){
        initMonthly(mLabels, mValues);
      } else {
        if (!monthlyChart) initMonthly([], []);
      }
    }).fail(function(){
      /* Silencioso: no rompemos el canvas si falla */
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

    // Si el tema redimensiona, actualiza el canvas
    let ro;
    const box = document.querySelector('.odo-chart-box');
    if (box && 'ResizeObserver' in window){
      ro = new ResizeObserver(() => {
        if (lineChart) lineChart.resize();
        if (donutChart) donutChart.resize();
        if (monthlyChart) monthlyChart.resize();
      });
      ro.observe(box);
    }

    // Primer render con pequeño defer para asegurar layout
    setTimeout(refresh, 50);
  });
})(jQuery);
<?php
$monthly_map = [];
foreach ($ids as $pid) {
    $start = get_post_meta($pid, 'fecha_hora_inicio', true);
    if (!$start) continue;
    $ts = strtotime($start);
    if (!$ts) continue;
    $key = date('Y-m', $ts);
    if (!isset($monthly_map[$key])) $monthly_map[$key] = 0;
    $monthly_map[$key]++;
}
ksort($monthly_map);
$monthly_labels = [];
$monthly_values = [];
foreach ($monthly_map as $ym => $count) {
    $label_ts = strtotime($ym . '-01');
    $monthly_labels[] = date_i18n('M Y', $label_ts);
    $monthly_values[] = (int) $count;
}
$data['monthly'] = [
    'labels' => $monthly_labels,
    'values' => $monthly_values,
];
