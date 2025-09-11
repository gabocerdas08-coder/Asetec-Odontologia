jQuery(function($){
  const $from = $('#odo_from');
  const $to   = $('#odo_to');
  const $q    = $('#odo_q');
  const $btn  = $('#odo_refresh');
  const $btnExport = $('#odo_export');
  const $log  = $('#dash_log');

  const setVal = (id, val) => { $('#'+id).text(val); };
  const getStates = () => $('#odo_states input[type=checkbox]:checked').map((_,el)=>el.value).get();

  function log(msg, isErr){
    $log.toggleClass('is-error', !!isErr).text(msg || '');
  }

  function todayStr(d=new Date()){
    const z = n => String(n).padStart(2,'0');
    return d.getFullYear()+'-'+z(d.getMonth()+1)+'-'+z(d.getDate());
  }

  // Prefill: semana actual
  const now = new Date();
  const start = new Date(now); start.setDate(now.getDate()-6);
  $from.val( todayStr(start) );
  $to.val( todayStr(now) );

  // ---- KPIs ----
  function loadKPIs(){
    log(ASETEC_ODO_DASH.i18n.loading);
    const data = {
      action: 'asetec_odo_dash_kpis',
      nonce: ASETEC_ODO_DASH.nonce,
      from: $from.val(),
      to: $to.val(),
      q: $q.val(),
      states: getStates()
    };
    return $.post(ASETEC_ODO_DASH.ajax, data)
      .done(function(res){
        if(!res || !res.success || !res.data || !res.data.kpis){ log(ASETEC_ODO_DASH.i18n.error,true); return; }
        const k = res.data.kpis;
        setVal('kpi_total', k.total ?? '0');
        setVal('kpi_pendiente', k.pendiente ?? '0');
        setVal('kpi_aprobada', k.aprobada ?? '0');
        setVal('kpi_realizada', k.realizada ?? '0');
        setVal('kpi_cancelada_usuario', k.cancelada_usuario ?? '0');
        setVal('kpi_cancelada_admin', k.cancelada_admin ?? '0');
        setVal('kpi_reprogramada', k.reprogramada ?? '0');
        log('');
      })
      .fail(()=> log(ASETEC_ODO_DASH.i18n.error,true));
  }

  // ---- Charts ----
  let chartTotal, chartStates;

  function buildTotal(labels, series){
    const ctx = document.getElementById('chart_total');
    if(chartTotal) chartTotal.destroy();
    chartTotal = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Citas', data: series, tension: .25 }] },
      options: { responsive:true, maintainAspectRatio:false }
    });
  }

  function buildStates(obj){
    const ctx = document.getElementById('chart_states');
    if(chartStates) chartStates.destroy();
    const labels = Object.keys(obj);
    const data = Object.values(obj);
    chartStates = new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Acumulado', data }] },
      options: { responsive:true, maintainAspectRatio:false }
    });
  }

  function loadSeries(){
    const data = {
      action: 'asetec_odo_dash_series',
      nonce: ASETEC_ODO_DASH.nonce,
      from: $from.val(),
      to: $to.val(),
      q: $q.val(),
      states: getStates()
    };
    return $.post(ASETEC_ODO_DASH.ajax, data)
      .done(function(res){
        if(!res || !res.success || !res.data){ return; }
        buildTotal(res.data.labels || [], res.data.total || []);
        buildStates(res.data.states || {});
      });
  }

  // ---- Export ----
  function exportCSV(){
    log(ASETEC_ODO_DASH.i18n.loading);
    const data = {
      action: 'asetec_odo_dash_export',
      nonce: ASETEC_ODO_DASH.nonce,
      from: $from.val(),
      to: $to.val(),
      q: $q.val(),
      states: getStates()
    };
    $.post(ASETEC_ODO_DASH.ajax, data)
      .done(function(res){
        if(!res || !res.success || !res.data || !res.data.csv){ log(ASETEC_ODO_DASH.i18n.error,true); return; }
        // descarga
        const blob = new Blob([res.data.csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href = url;
        a.download = 'reporte_odontologia.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        log('');
      })
      .fail(()=> log(ASETEC_ODO_DASH.i18n.error,true));
  }

  // Handlers
  $btn.on('click', function(e){
    e.preventDefault();
    $.when(loadKPIs(), loadSeries());
  });

  $btnExport.on('click', function(e){
    e.preventDefault();
    exportCSV();
  });

  // Primera carga
  $.when(loadKPIs(), loadSeries());
});
