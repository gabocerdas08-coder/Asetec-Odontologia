(function($){
  const $doc = $(document);
  let chSeries, chPie, chHours;

  function getStates(){
    const s = [];
    $('.odo-dash .st:checked').each(function(){ s.push($(this).val()); });
    return s;
  }
  function getRange(){
    const from = $('#odo_from').val();
    const to   = $('#odo_to').val();
    // si no hay custom, que backend use el preset actual guardado en data-range-active
    const range = $('.odo-dash__range .quick button.active').data('range') || 30;
    return {from, to, range};
  }

  function block(){ $('.odo-dash').addClass('loading'); }
  function unblock(){ $('.odo-dash').removeClass('loading'); }

  function fetchSummary(){
    return $.post(ASETEC_ODO_DASH.ajax, {
      action: 'asetec_odo_dash_summary',
      nonce: ASETEC_ODO_DASH.nonce,
      states: getStates(),
      ...getRange()
    });
  }
  function fetchSeries(){
    return $.post(ASETEC_ODO_DASH.ajax, {
      action: 'asetec_odo_dash_series',
      nonce: ASETEC_ODO_DASH.nonce,
      states: getStates(),
      ...getRange()
    });
  }

  function drawKpis(data){
    $('#kpi_total').text(data.total);
    $('#kpi_aprobadas').text(data.by_estado.aprobada ?? 0);
    $('#kpi_realizadas').text(data.by_estado.realizada ?? 0);
    const canc = (data.by_estado.cancelada_usuario ?? 0) + (data.by_estado.cancelada_admin ?? 0);
    $('#kpi_canceladas').text(canc);
    $('#kpi_reprogramadas').text(data.by_estado.reprogramada ?? 0);
    $('#kpi_promdia').text(data.prom_dia);
  }

  function drawPie(data){
    const labels = Object.keys(data.by_estado);
    const values = labels.map(k=> data.by_estado[k]);
    const ctx = document.getElementById('ch_pie');
    if (chPie) chPie.destroy();
    chPie = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{ data: values }]
      },
      options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
    });
  }

  function drawHours(data){
    const hours = [];
    for(let h=0; h<24; h++){
      const key = (''+h).padStart(2,'0');
      hours.push(data.top_hours[key] || 0);
    }
    const ctx = document.getElementById('ch_hours');
    if (chHours) chHours.destroy();
    chHours = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: [...Array(24)].map((_,i)=> (''+i).padStart(2,'0')+':00'),
        datasets: [{ label:'Citas', data: hours }]
      },
      options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
    });
  }

  function drawSeries(payload){
    const labels = payload.labels;
    // payload.series: array[{estado->n}]
    const estados = Object.keys(payload.series[0] || {});
    const datasets = [];
    estados.forEach((st,idx)=>{
      datasets.push({
        label: st,
        data: payload.series.map(row => row[st]),
        fill:false,
        tension:0.2
      });
    });
    // total
    datasets.unshift({
      label: 'Total',
      data: payload.series.map(row => Object.values(row).reduce((a,b)=>a+b,0)),
      borderWidth: 2,
      tension: 0.2
    });

    const ctx = document.getElementById('ch_series');
    if (chSeries) chSeries.destroy();
    chSeries = new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive:true,
        interaction:{ mode:'index', intersect:false },
        scales:{ y:{ beginAtZero:true } }
      }
    });
  }

  function drawSummaryTable(data){
    const $t = $('#summary_table');
    let html = '<div class="grid">';
    html += '<div class="col"><h4>Top días</h4><ul>';
    (data.top_days || []).forEach(r=>{
      html += `<li><strong>${r.d}</strong> — ${r.n}</li>`;
    });
    html += '</ul></div>';

    html += '<div class="col"><h4>Top horas</h4><ul>';
    const hours = data.top_hours || {};
    Object.keys(hours).sort().forEach(h=>{
      html += `<li><strong>${h}:00</strong> — ${hours[h]}</li>`;
    });
    html += '</ul></div>';
    html += '</div>';
    $t.html(html);
  }

  function refresh(){
    block();
    $.when( fetchSummary(), fetchSeries() ).done(function(r1, r2){
      const s1 = r1[0], s2 = r2[0];
      if(s1 && s1.success){
        drawKpis(s1.data);
        drawPie(s1.data);
        drawHours(s1.data);
        drawSummaryTable(s1.data);
      }
      if(s2 && s2.success){
        drawSeries(s2.data);
      }
    }).always(unblock);
  }

  // Events
  $doc.on('click', '.odo-dash__range .quick button', function(){
    $('.odo-dash__range .quick button').removeClass('active');
    $(this).addClass('active');
    // borra custom para que el backend use el preset
    $('#odo_from, #odo_to').val('');
    refresh();
  });
  $doc.on('click', '#odo_apply', function(){
    $('.odo-dash__range .quick button').removeClass('active');
    refresh();
  });
  $doc.on('change', '.odo-dash .st', refresh);

  // Export
  $doc.on('click', '#odo_export', function(){
    const form = $('<form>', {method:'POST', action:ASETEC_ODO_DASH.ajax, target:'_blank'}).appendTo('body');
    form.append($('<input>', {type:'hidden', name:'action', value:'asetec_odo_dash_export'}));
    form.append($('<input>', {type:'hidden', name:'nonce', value:ASETEC_ODO_DASH.nonce}));
    const r = getRange();
    form.append($('<input>', {type:'hidden', name:'from', value:r.from}));
    form.append($('<input>', {type:'hidden', name:'to', value:r.to}));
    form.append($('<input>', {type:'hidden', name:'range', value:r.range}));
    getStates().forEach(st=>{
      form.append($('<input>', {type:'hidden', name:'states[]', value:st}));
    });
    form[0].submit();
    setTimeout(()=>form.remove(), 1000);
  });

  // boot
  $(function(){ refresh(); });

})(jQuery);
