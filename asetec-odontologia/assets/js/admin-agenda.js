(function($){
  $(function(){
    var el = document.getElementById('odo-calendar');
    if(!el){ console.warn('ASETEC ODO: #odo-calendar no existe'); return; }

    // ---------- helpers UI ----------
    function openModal(title){
      $('#odo-modal-title').text(title || '');
      $('#odo-modal').attr('aria-hidden','false').addClass('is-open');
    }
    function closeModal(){
      $('#odo-modal').attr('aria-hidden','true').removeClass('is-open');
      var f = document.getElementById('odo-form');
      if (f) f.reset();
      $('#odo_post_id').val('');
      $('#odo_estado').val('');
      $('#odo-btn-save').show();
      $('#odo-btn-update').hide();
      $('#odo-btn-approve').show();
      $('#odo-btn-cancel').show();
      $('#odo-btn-done').show();
    }
    $(document).on('click','#odo-btn-close,.odo-modal__backdrop', closeModal);

    function toLocalInput(dtStr){
      if(!dtStr) return '';
      return dtStr.replace(' ', 'T').slice(0,16);
    }
    function fromLocalInput(val){
      if(!val) return '';
      return val.replace('T',' ') + ':00';
    }
    function estadoColor(s){
      return {
        pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981',
        cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'
      }[s] || '#64748b';
    }

    // ---------- FullCalendar ----------
    var cal = new FullCalendar.Calendar(el, {
      initialView: 'timeGridWeek',
      expandRows: true,
      slotMinTime: '07:00:00',
      slotMaxTime: '20:30:00',
      allDaySlot: false,
      contentHeight: 'auto',
      nowIndicator: true,
      locale: 'es',
      slotDuration: '00:10:00',
      slotLabelInterval: '00:30:00',
      slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
      eventMinHeight: 22,
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },

      selectable: true,
      editable: true,

      // *** Blindaje para que nunca quede en spinner ***
      events: function(info, success){
        var timedOut = false;
        var to = setTimeout(function(){
          timedOut = true;
          console.warn('ASETEC ODO: timeout eventos → mostrando vacío');
          success([]);
        }, 7000); // 7s de margen

        $.post(ASETEC_ODO_ADMIN.ajax, {
          action:'asetec_odo_events',
          nonce: ASETEC_ODO_ADMIN.nonce,
          start: info.startStr,
          end: info.endStr
        })
        .done(function(r){
          if (timedOut) return;
          clearTimeout(to);
          if(r && r.success && r.data && Array.isArray(r.data.events)){
            var evs = r.data.events.map(function(ev){
              var est = ev.extendedProps && ev.extendedProps.estado;
              if(est){ ev.backgroundColor = estadoColor(est); ev.borderColor = ev.backgroundColor; }
              ev.display = 'block';
              return ev;
            });
            success(evs);
          } else {
            console.warn('ASETEC ODO: respuesta inválida de eventos', r);
            success([]);
          }
        })
        .fail(function(xhr){
          if (timedOut) return;
          clearTimeout(to);
          console.error('ASETEC ODO: fallo AJAX eventos', xhr.status, xhr.responseText);
          success([]);
        });
      },

      select: function(sel){
        $('#odo_start').val( toLocalInput(sel.startStr.replace('Z','').replace('T',' ')) );
        $('#odo_end').val( toLocalInput(sel.endStr  .replace('Z','').replace('T',' ')) );
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
          $('#odo_end').val( toLocalInput((d.end||'').replace('T',' ')) );
          $('#odo_nombre').val( d.nombre || '' );
          $('#odo_cedula').val( d.cedula || '' );
          $('#odo_correo').val( d.correo || '' );
          $('#odo_telefono').val( d.telefono || '' );
          $('#odo_estado').val( d.estado || '' );
          $('#odo-btn-save').hide(); $('#odo-btn-update').show();
          $('#odo-btn-approve,#odo-btn-done,#odo-btn-cancel').show();
          openModal('Cita');
        })
        .fail(function(xhr){
          alert('Error al cargar la cita');
          console.error('ASETEC ODO: fallo show', xhr.status, xhr.responseText);
        });
      },

      eventDrop: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = (info.event.endStr||'').replace('Z','');
        if(!id || !start){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo reprogramar'); info.revert(); return; }
          cal.refetchEvents();
        });
      },

      eventResize: function(info){
        var id = info.event.extendedProps.post_id;
        var start = info.event.startStr.replace('Z','');
        var end   = info.event.endStr.replace('Z','');
        if(!id || !start || !end){ info.revert(); return; }
        $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN.nonce, id:id, start:start, end:end }, function(r){
          if(!r.success){ alert(r.data && r.data.msg || 'No se pudo ajustar'); info.revert(); return; }
          cal.refetchEvents();
        });
      }
    });

    cal.render();

    // ----- acciones modal -----
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
        closeModal(); cal.refetchEvents();
      }).fail(function(xhr){
        alert('Error al crear');
        console.error('ASETEC ODO: fallo create', xhr.status, xhr.responseText);
      });
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
        closeModal(); cal.refetchEvents();
      }).fail(function(xhr){
        alert('Error al actualizar');
        console.error('ASETEC ODO: fallo reschedule', xhr.status, xhr.responseText);
      });
    });

    $('#odo-btn-approve').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); cal.refetchEvents();
      });
    });

    $('#odo-btn-cancel').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      if(!confirm('¿Seguro que desea cancelar esta cita?')) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); cal.refetchEvents();
      });
    });

    $('#odo-btn-done').on('click', function(){
      var id = $('#odo_post_id').val(); if(!id) return;
      $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
        if(!r.success){ alert(r.data && r.data.msg || 'Error'); return; }
        closeModal(); cal.refetchEvents();
      });
    });
  });
})(jQuery);
