jQuery(function($){
  const $from = $('#odo_from');
  const $to   = $('#odo_to');
  const $btn  = $('#odo_refresh');
  const $log  = $('#dash_log');

  const setVal = (id, val) => { $('#'+id).text(val); };

  function log(msg, isErr){
    $log
      .toggleClass('is-error', !!isErr)
      .text(msg);
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

  function loadKPIs(){
    log(ASETEC_ODO_DASH.i18n.loading);
    const data = {
      action: 'asetec_odo_dash_kpis',
      nonce: ASETEC_ODO_DASH.nonce,
      from: $from.val(),
      to: $to.val()
    };
    $.post(ASETEC_ODO_DASH.ajax, data)
      .done(function(res){
        if(!res || !res.success || !res.data || !res.data.kpis){
          log(ASETEC_ODO_DASH.i18n.error, true);
          return;
        }
        const k = res.data.kpis;
        setVal('kpi_total', k.total ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_pendiente', k.pendiente ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_aprobada', k.aprobada ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_realizada', k.realizada ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_cancelada_usuario', k.cancelada_usuario ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_cancelada_admin', k.cancelada_admin ?? ASETEC_ODO_DASH.i18n.na);
        setVal('kpi_reprogramada', k.reprogramada ?? ASETEC_ODO_DASH.i18n.na);
        log('');
      })
      .fail(function(){
        log(ASETEC_ODO_DASH.i18n.error, true);
      });
  }

  $btn.on('click', function(e){ e.preventDefault(); loadKPIs(); });

  // Primera carga
  loadKPIs();
});
