(function($){
  $(function(){
    var el = document.getElementById('odo-calendar');
    if(!el) return;

    function estadoColor(estado){
      // Colores por estado
      switch(estado){
        case 'pendiente': return '#f59e0b'; // ámbar
        case 'aprobada': return '#3b82f6';  // azul
        case 'realizada': return '#10b981'; // verde
        case 'cancelada_usuario':
        case 'cancelada_admin': return '#ef4444'; // rojo
        case 'reprogramada': return '#8b5cf6'; // violeta
        default: return '#64748b'; // gris
      }
    }

    var calendar = new FullCalendar.Calendar(el, {
      initialView: 'timeGridWeek',
      slotMinTime: '07:00:00',
      slotMaxTime: '20:00:00',
      allDaySlot: false,
      nowIndicator: true,
      selectable: true,     // <- permite selección para crear
      editable: true,       // <- permite drag/resize para reprogramar
      locale: 'es',
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },

      events: function(info, success, failure){
        $.post(ASETEC_ODO_ADMIN.ajax, {
          action: 'asetec_odo_events',
          nonce: ASETEC_ODO_ADMIN.nonce,
          start: info.startStr,
          end: info.endStr
        }, function(res){
          if(!res.success) { failure(res.data && res.data.msg || 'Error'); return; }
          var evs = (res.data.events || []).map(function(ev){
            // colorear por estado
            if(ev.extendedProps && ev.extendedProps.estado){
              ev.backgroundColor = estadoColor(ev.extendedProps.estado);
              ev.borderColor = ev.backgroundColor;
            }
            return ev;
          });
          success(evs);
        });
      },

      // Crear cita seleccionando rango
      select: function(sel){
        var start = sel.startStr.replace('Z','');
        var end   = sel.endStr.replace('Z','');

        // Formulario rápido con prompts (puedes reemplazar por modal custom)
        var nombre = prompt('Nombre completo del paciente:'); if(!nombre) return;
        var cedula = prompt('Cédula:'); if(!cedula) return;
        var correo = prompt('Correo:'); if(!correo) return;
        var tel    = prompt('Teléfono:'); if(!tel) return;

        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_create',
          nonce: ASETEC_ODO_ADMIN.nonce,
          start: start,
          end: end,
          nombre: nombre,
          cedula: cedula,
          correo: correo,
          telefono: tel
        }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo crear'); return; }
          alert('Cita creada ('+r.data.estado+').');
          calendar.refetchEvents();
        });
      },

      // Click en evento: aprobar/cancelar/realizar
      eventClick: function(info){
        var id = info.event.extendedProps.post_id;
        var estado = info.event.extendedProps.estado || '';
        if(!id) return;

        // Menú simple por confirmaciones (rápido de implementar)
        if(estado === 'pendiente'){
          if(confirm('Aprobar esta cita?')){
            $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
              if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
              calendar.refetchEvents();
            });
            return;
          }
        }
        if(confirm('¿Cancelar esta cita?')){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
            calendar.refetchEvents();
          });
        } else if(confirm('¿Marcar como realizada?')){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
            calendar.refetchEvents();
          });
        }
      },

      // Drag para reprogramar (mover)
      eventDrop: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = info.event.endStr ? info.event.endStr.replace('Z','') : null;
        if(!id || !start){ info.revert(); return; }
        if(!end){
          // si el evento no trae end, usa duración original (FullCalendar lo suele traer)
          var durMin = prompt('Duración en minutos para reprogramar:', '40');
          if(!durMin){ info.revert(); return; }
          var d = new Date(info.event.start.getTime() + parseInt(durMin,10)*60000);
          end = d.toISOString().slice(0,19);
        }
        if(!confirm('Confirmar reprogramación?')) { info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_reschedule',
          nonce: ASETEC_ODO_ADMIN.nonce,
          id: id, start: start, end: end
        }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo reprogramar'); info.revert(); return; }
          calendar.refetchEvents();
        });
      },

      // Resize para cambiar duración
      eventResize: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = info.event.endStr ? info.event.endStr.replace('Z','') : null;
        if(!id || !start || !end){ info.revert(); return; }
        if(!confirm('Confirmar nueva duración?')) { info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_reschedule',
          nonce: ASETEC_ODO_ADMIN.nonce,
          id: id, start: start, end: end
        }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo ajustar'); info.revert(); return; }
          calendar.refetchEvents();
        });
      }
    });

    calendar.render();
  });
})(jQuery);
