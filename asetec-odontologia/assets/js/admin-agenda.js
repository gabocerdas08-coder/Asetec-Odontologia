(function($){
  $(function(){
    var el = document.getElementById('odo-calendar');
    if(!el) return;

    function estadoColor(s){
      return {
        pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981',
        cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'
      }[s] || '#64748b';
    }

    var cal = new FullCalendar.Calendar(el, {
      initialView: 'timeGridWeek',
      slotMinTime: '07:00:00',
      slotMaxTime: '20:00:00',
      allDaySlot: false,
      nowIndicator: true,
      locale: 'es',
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },

      // === HABILITA CREAR Y MOVER ===
      selectable: true,   // crear al seleccionar rango
      editable: true,     // drag & resize para reprogramar

      events: function(info, ok, fail){
        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_events',
          nonce: ASETEC_ODO_ADMIN.nonce,
          start: info.startStr,
          end: info.endStr
        }, function(r){
          if(!r.success){ fail(r.data && r.data.msg || 'Error'); return; }
          var evs = (r.data.events||[]).map(function(ev){
            var est = ev.extendedProps && ev.extendedProps.estado;
            if(est){ ev.backgroundColor = estadoColor(est); ev.borderColor = ev.backgroundColor; }
            return ev;
          });
          ok(evs);
        });
      },

      // === CREAR CITA (selección de rango) ===
      select: function(sel){
        var start = sel.startStr.replace('Z','');
        var end   = sel.endStr.replace('Z','');

        var nombre = prompt('Nombre completo del paciente:'); if(!nombre) return;
        var cedula = prompt('Cédula:'); if(!cedula) return;
        var correo = prompt('Correo:'); if(!correo) return;
        var tel    = prompt('Teléfono:'); if(!tel) return;

        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_create', nonce:ASETEC_ODO_ADMIN.nonce,
          start:start, end:end, nombre:nombre, cedula:cedula, correo:correo, telefono:tel
        }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo crear'); return; }
          alert('Cita creada ('+r.data.estado+').');
          cal.refetchEvents();
        });
      },

      // === ACCIONES SOBRE UN EVENTO ===
      eventClick: function(info){
        var id = info.event.extendedProps.post_id;
        var estado = info.event.extendedProps.estado || '';
        if(!id) return;

        if(estado==='pendiente' && confirm('¿Aprobar esta cita?')){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
            cal.refetchEvents();
          });
          return;
        }
        if(confirm('¿Cancelar esta cita?')){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
            cal.refetchEvents();
          });
        } else if(confirm('¿Marcar como realizada?')){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
            cal.refetchEvents();
          });
        }
      },

      // === REPROGRAMAR: mover ===
      eventDrop: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = (info.event.endStr||'').replace('Z','');
        if(!id || !start){ info.revert(); return; }
        if(!end){
          var dur = prompt('Duración nueva (minutos):','40'); if(!dur){ info.revert(); return; }
          var d = new Date(info.event.start.getTime() + parseInt(dur,10)*60000);
          end = d.toISOString().slice(0,19);
        }
        if(!confirm('Confirmar reprogramación?')){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo reprogramar'); info.revert(); return; }
          cal.refetchEvents();
        });
      },

      // === REPROGRAMAR: cambiar duración ===
      eventResize: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = info.event.endStr.replace('Z','');
        if(!id || !start || !end){ info.revert(); return; }
        if(!confirm('Confirmar nueva duración?')){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo ajustar'); info.revert(); return; }
          cal.refetchEvents();
        });
      }
    });

    cal.render();
  });
})(jQuery);
