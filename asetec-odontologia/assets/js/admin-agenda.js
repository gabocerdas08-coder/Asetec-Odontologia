(function($){
  $(function(){

    // Mover modal a <body> para evitar clipping / z-index con Wooffice/Elementor
    var $modal = $('#odo-modal');
    if($modal.length){ $modal.appendTo('body'); }

    var el = document.getElementById('odo-calendar');
    if(!el){ console.warn('ASETEC ODO: #odo-calendar no existe'); return; }
    if (typeof window.FullCalendar === 'undefined') {
      console.error('ASETEC ODO: FullCalendar no está disponible.');
      $('#odo-calendar').html('<p style="padding:12px;color:#b91c1c">No se pudo cargar el calendario (FullCalendar).</p>');
      return;
    }

    // —— utilidades UI ——
    function toast(msg){
      var $t = $('#odo-toast');
      $t.text(msg||'').addClass('show');
      setTimeout(function(){ $t.removeClass('show'); }, 1800);
    }
    function openModal(title){ $('#odo-modal-title').text(title||''); $('#odo-modal').attr('aria-hidden','false').addClass('is-open'); }
    function closeModal(){ $('#odo-modal').attr('aria-hidden','true').removeClass('is-open'); var f=document.getElementById('odo-form'); if(f) f.reset(); $('#odo_post_id').val(''); $('#odo_estado').val(''); $('#odo-btn-save').show(); $('#odo-btn-update').hide(); $('#odo-btn-approve,#odo-btn-cancel,#odo-btn-done').show(); $('#odo_custom_wrap').hide(); $('#odo_duration').val('40'); }
    $(document).on('click','#odo-btn-close,.odo-modal__backdrop', closeModal);

    function toLocalInput(dtStr){ if(!dtStr) return ''; return dtStr.replace(' ', 'T').slice(0,16); }
    function fromLocalInput(val){ if(!val) return ''; return val.replace('T',' ') + ':00'; }
    function estadoColor(s){ return { pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981', cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6' }[s] || '#64748b'; }
    function estadoChipClass(s){ return 'chip-' + (s||'').replace('_','_'); }

    // Duración dinámica
    function getDurationMinutes(){
      var v = $('#odo_duration').val();
      if(v === 'custom'){
        var n = parseInt($('#odo_custom_minutes').val(),10);
        return isNaN(n) || n<10 ? 40 : n;
      }
      return parseInt(v,10) || 40;
    }
    function addMinutes(date, mins){
      var d = new Date(date); d.setMinutes(d.getMinutes() + mins); return d;
    }
    function roundToNext10(date){
      var d = new Date(date);
      d.setSeconds(0,0);
      var m = d.getMinutes();
      var extra = (10 - (m % 10)) % 10;
      d.setMinutes(m + extra);
      return d;
    }
    function defaultStartEnd(){
      var now = roundToNext10(new Date());
      var startHour = 7, endHour = 20;
      if (now.getHours() < startHour) now.setHours(startHour,0,0,0);
      if (now.getHours() >= endHour) { now.setDate(now.getDate()+1); now.setHours(startHour,0,0,0); }
      var dur = getDurationMinutes();
      return { start: now, end: addMinutes(now, dur) };
    }
    function recalcEndFromStart(){
      var sVal = $('#odo_start').val();
      if(!sVal) return;
      var dur = getDurationMinutes();
      var end = addMinutes(new Date(sVal.replace('T',' ')), dur);
      var iso = end.toISOString().slice(0,19).replace('T',' ');
      $('#odo_end').val(toLocalInput(iso));
    }
    $('#odo_duration').on('change', function(){
      $('#odo_custom_wrap').toggle(this.value === 'custom');
      recalcEndFromStart();
    });
    $('#odo_custom_minutes,#odo_start').on('input change', recalcEndFromStart);

    // —— filtros por estado ——
    var estadosVisibles = new Set(['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada']);
    $(document).on('change','.odo-filter', function(){
      var v = this.value;
      if(this.checked) estadosVisibles.add(v); else estadosVisibles.delete(v);
      calendar.refetchEvents(); // trigger refresh para todas las vistas
    });

    // —— búsqueda cliente ——
    var searchQuery = '';
    $('#odo-search').on('input', function(){
      searchQuery = (this.value || '').toLowerCase().trim();
      calendar.rerenderEvents(); // forzar eventDidMount con nuestro filtro
    });

    // Lógica de visibilidad unificada
    function applyVisibility(event, el){
      var est = (event.extendedProps && event.extendedProps.estado) || '';
      var title = (event.title || '');
      var ci = (event.extendedProps && event.extendedProps.cedula) || '';
      var matchEstado = estadosVisibles.has(est || '');
      var matchQuery  = !searchQuery || title.toLowerCase().includes(searchQuery) || ci.toLowerCase().includes(searchQuery);
      el.style.display = (matchEstado && matchQuery) ? '' : 'none';
    }

    // —— Calendar ——
    var calendar = new FullCalendar.Calendar(el, {
      initialView: 'timeGridWeek',
      allDaySlot: false,
      nowIndicator: true,
      locale: 'es',
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },

      businessHours: [{ daysOfWeek:[1,2,3,4,5], startTime:'07:00', endTime:'20:00' }],
      slotMinTime: '07:00:00',
      slotMaxTime: '20:30:00',
      slotDuration: '00:10:00',
      slotLabelInterval: '00:30:00',
      slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      snapDuration: '00:10:00',
      eventMinHeight: 22,
      expandRows: true,

      events: function(info, success, failure){
        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_events', nonce:ASETEC_ODO_ADMIN.nonce,
          start:info.startStr, end:info.endStr
        }, function(res){
          if(!res || !res.success || !res.data){ failure('Error'); return; }
          var evs = (res.data.events || []).map(function(ev){
            var est = ev.extendedProps && ev.extendedProps.estado;
            if(est){ ev.backgroundColor = estadoColor(est); ev.borderColor = ev.backgroundColor; }
            ev.display='block';
            return ev;
          });
          success(evs);
        }).fail(function(){ failure('AJAX falló'); });
      },

      // chips + aplicar filtros/búsqueda en cada render
      eventContent: function(arg){
        var est = (arg.event.extendedProps && arg.event.extendedProps.estado) || '';
        var nombre = (arg.event.title || '').trim() || '(Sin nombre)';
        var chip = $('<span class="odo-chip '+estadoChipClass(est)+'"></span>').text(est.replace('_',' '));
        var name = $('<span></span>').text(' '+nombre);
        var $wrap = $('<div></div>').append(chip).append(name);
        return { domNodes:[ $wrap.get(0) ] };
      },
      eventDidMount: function(info){
        applyVisibility(info.event, info.el);
      },

      selectable: true,
      select: function(sel){
        var now = new Date();
        if (sel.start < now) { toast('No puede crear citas en el pasado'); return; }
        $('#odo_start').val( toLocalInput(sel.startStr.replace('Z','').replace('T',' ')) );
        $('#odo_end').val( toLocalInput(sel.endStr  .replace('Z','').replace('T',' ')) );
        $('#odo_estado').val('pendiente');
        $('#odo-btn-approve,#odo-btn-done,#odo-btn-cancel').hide();
        $('#odo-btn-save').show(); $('#odo-btn-update').hide();
        openModal('Nueva cita');
      },

      dateClick: function(info){
        if(info.view.type.indexOf('timeGrid') === -1) return;
        var d = new Date(info.date);
        if (d < new Date()) { toast('No puede crear citas en el pasado'); return; }
        var dur = getDurationMinutes();
        $('#odo_start').val( toLocalInput(info.dateStr.replace('Z','').replace('T',' ')) );
        $('#odo_end').val( toLocalInput(addMinutes(d, dur).toISOString().slice(0,19).replace('T',' ')) );
        $('#odo_estado').val('pendiente');
        $('#odo-btn-approve,#odo-btn-done,#odo-btn-cancel').hide();
        $('#odo-btn-save').show(); $('#odo-btn-update').hide();
        openModal('Nueva cita');
      },

      eventClick: function(info){
        var id = info.event.extendedProps && info.event.extendedProps.post_id;
        if(!id) return;
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_show', nonce:ASETEC_ODO_ADMIN.nonce, id:id })
        .done(function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
          var d = r.data || {};
          $('#odo_post_id').val(id);
          $('#odo_start').val( toLocalInput((d.start||'').replace('T',' ')) );
          $('#odo_end').val( toLocalInput((d.end  ||'').replace('T',' ')) );
          $('#odo_nombre').val( d.nombre || '' );
          $('#odo_cedula').val( d.cedula || '' );
          $('#odo_correo').val( d.correo || '' );
          $('#odo_telefono').val( d.telefono || '' );
          $('#odo_estado').val( d.estado || '' );
          $('#odo-btn-save').hide(); $('#odo-btn-update').show();
          $('#odo-btn-approve,#odo-btn-done,#odo-btn-cancel').show();
          openModal('Cita');
        })
        .fail(function(xhr){ alert('Error al cargar la cita'); console.error('ASETEC ODO: fallo show', xhr.status); });
      },

      editable: true,
      eventDrop: function(info){
        var id = info.event.extendedProps && info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = (info.event.endStr||'').replace('Z','');
        if(!id || !start){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo reprogramar'); info.revert(); return; }
          toast('Cita reprogramada'); calendar.refetchEvents();
        });
      },
      eventResize: function(info){
        var id = info.event.extendedProps && info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = info.event.endStr.replace('Z','');
        if(!id || !start || !end){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo ajustar'); info.revert(); return; }
          toast('Duración actualizada'); calendar.refetchEvents();
        });
      }
    });

    calendar.render();

    // Botón "Nueva cita"
    $('#odo-btn-new').on('click', function(){
      var t = defaultStartEnd();
      var startISO = toLocalInput(t.start.toISOString().slice(0,19).replace('T',' '));
      var endISO   = toLocalInput(t.end  .toISOString().slice(0,19).replace('T',' '));
      $('#odo_start').val(startISO);
      $('#odo_end').val(endISO);
      $('#odo_estado').val('pendiente');
      $('#odo-btn-approve,#odo-btn-done,#odo-btn-cancel').hide();
      $('#odo-btn-save').show(); $('#odo-btn-update').hide();
      openModal('Nueva cita');
    });

    // Acciones del modal
    $('#odo-btn-save').on('click', function(){
      var payload = {
        action:'asetec_odo_create', nonce:ASETEC_ODO_ADMIN.nonce,
        start: fromLocalInput($('#odo_start').val()),
        end:   fromLocalInput($('#odo_end').val()),
        nombre: $('#odo_nombre').val(),
        cedula: $('#odo_cedula').val(),
        correo: $('#odo_correo').val(),
        telefono: $('#odo_telefono').val()
      };
      $.post(ASETEC_ODO_ADMIN.ajax, payload, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'No se pudo crear'); return; }
        closeModal(); toast('Cita creada'); calendar.refetchEvents();
      }).fail(function(){ alert('Error al crear'); });
    });

    $('#odo-btn-update').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      var payload = {
        action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id,
        start: fromLocalInput($('#odo_start').val()),
        end:   fromLocalInput($('#odo_end').val())
      };
      $.post(ASETEC_ODO_ADMIN.ajax, payload, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'No se pudo actualizar'); return; }
        closeModal(); toast('Cita actualizada'); calendar.refetchEvents();
      }).fail(function(){ alert('Error al actualizar'); });
    });

    $('#odo-btn-approve').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); toast('Cita aprobada'); calendar.refetchEvents();
      });
    });

    $('#odo-btn-cancel').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      if(!confirm('¿Seguro que desea cancelar esta cita?')) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); toast('Cita cancelada'); calendar.refetchEvents();
      });
    });

    $('#odo-btn-done').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); toast('Marcada como realizada'); calendar.refetchEvents();
      });
    });

  });
})(jQuery);
