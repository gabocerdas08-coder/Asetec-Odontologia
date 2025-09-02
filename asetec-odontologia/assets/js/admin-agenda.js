(function($){
  $(function(){
    var el = document.getElementById('odo-calendar');
    if(!el) return;
    var calendar = new FullCalendar.Calendar(el, {
      initialView: 'timeGridWeek',
      slotMinTime: '07:00:00',
      slotMaxTime: '20:00:00',
      allDaySlot: false,
      nowIndicator: true,
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
          success(res.data.events || []);
        });
      },
      eventClick: function(info){
        var ev = info.event;
        var id = ev.extendedProps.post_id;
        if(!id) return;
        if(confirm(ASETEC_ODO_ADMIN.i18n.confirm_approve)){
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success) { alert(r.data && r.data.msg || 'Error'); return; }
            calendar.refetchEvents();
          });
        } else if (confirm(ASETEC_ODO_ADMIN.i18n.confirm_cancel)) {
          $.post(ASETEC_ODO_ADMIN.ajax, { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN.nonce, id:id }, function(r){
            if(!r.success) { alert(r.data && r.data.msg || 'Error'); return; }
            calendar.refetchEvents();
          });
        }
      }
    });
    calendar.render();
  });
})(jQuery);