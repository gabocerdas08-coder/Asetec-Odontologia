<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('ASETEC_ODO_Shortcode_Admin_Agenda') ) {

class ASETEC_ODO_Shortcode_Admin_Agenda {
    public function __construct(){
        add_shortcode( 'odo_admin_agenda', [ $this, 'render' ] );
    }

    public function render(){
        if ( ! current_user_can('manage_options') ) {
            return '<p>No autorizado.</p>';
        }

        // Variables necesarias para AJAX
        $ajax  = esc_url( admin_url('admin-ajax.php') );
        $nonce = esc_attr( wp_create_nonce('asetec_odo_admin') );

        ob_start(); ?>
<!— ASETEC Agenda Lite —>
<style>
  .odo-calendar .fc { --fc-border-color:#e5e7eb; font-family: Inter,system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif; }
  .odo-calendar .fc-timegrid-slot-label { font-size:12px; color:#374151; }
  .odo-calendar .fc-toolbar-title { font-weight:700; letter-spacing:0.2px; }
  .odo-calendar .fc-button { border-radius:10px; padding:6px 10px; }
  .odo-calendar .fc-event { border-radius:10px; padding:2px 6px; font-size:12px; }
  .odo-calendar .fc-timegrid-slot { height:1.6em; }
  .odo-calendar { min-height:640px; }

  /* Modal */
  .odo-modal { position:fixed; inset:0; z-index:9999; display:none; }
  .odo-modal.is-open { display:block; }
  .odo-modal__backdrop { position:absolute; inset:0; background:rgba(15,23,42,.45); }
  .odo-modal__dialog { position:relative; max-width:720px; margin:6vh auto; background:#fff; border-radius:14px; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; }
  .odo-modal__header { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid #e5e7eb; }
  .odo-modal__body { padding:16px 18px; }
  .odo-modal__footer { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 18px; border-top:1px solid #e5e7eb; background:#fafafa; }
  .odo-actions-left,.odo-actions-right { display:flex; gap:8px; flex-wrap:wrap; }
  .odo-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; } @media (max-width:680px){ .odo-grid{ grid-template-columns:1fr; } }
  .odo-field label { display:block; font-size:12px; color:#374151; margin:0 0 4px; }
  .odo-field input[type="text"],.odo-field input[type="email"],.odo-field input[type="tel"],.odo-field input[type="datetime-local"]{ width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; }
  .odo-btn { appearance:none; border:1px solid #d1d5db; background:#fff; color:#111827; padding:8px 12px; border-radius:10px; cursor:pointer; }
  .odo-btn:hover { background:#f3f4f6; }
  .odo-btn--primary { background:#1d4ed8; color:#fff; border-color:#1d4ed8; } .odo-btn--primary:hover { background:#1e40af; }
  .odo-btn--danger { background:#b91c1c; color:#fff; border-color:#b91c1c; } .odo-btn--danger:hover { background:#991b1b; }
  .odo-btn--warn { background:#059669; color:#fff; border-color:#059669; } .odo-btn--warn:hover { background:#047857; }
  .odo-btn--ghost { background:transparent; border:none; font-size:18px; color:#6b7280; } .odo-btn--ghost:hover { color:#111827; }
</style>

<div class="wrap modulo-asetec">
  <h2>Agenda Odontología (Lite)</h2>
  <div id="odo-calendar" class="odo-calendar"></div>
</div>

<!-- Modal -->
<div id="odo-modal" class="odo-modal" aria-hidden="true">
  <div class="odo-modal__backdrop"></div>
  <div class="odo-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="odo-modal-title">
    <div class="odo-modal__header">
      <h3 id="odo-modal-title"></h3>
      <button type="button" class="odo-btn odo-btn--ghost" id="odo-btn-close">✕</button>
    </div>
    <div class="odo-modal__body">
      <form id="odo-form">
        <input type="hidden" id="odo_post_id">
        <div class="odo-grid">
          <div class="odo-field">
            <label>Inicio</label>
            <input type="datetime-local" id="odo_start" required>
          </div>
          <div class="odo-field">
            <label>Fin</label>
            <input type="datetime-local" id="odo_end" required>
          </div>
        </div>
        <div class="odo-grid">
          <div class="odo-field">
            <label>Nombre completo</label>
            <input type="text" id="odo_nombre" required>
          </div>
          <div class="odo-field">
            <label>Cédula</label>
            <input type="text" id="odo_cedula" required>
          </div>
        </div>
        <div class="odo-grid">
          <div class="odo-field">
            <label>Correo</label>
            <input type="email" id="odo_correo" required>
          </div>
          <div class="odo-field">
            <label>Teléfono</label>
            <input type="tel" id="odo_telefono" required>
          </div>
        </div>
        <div class="odo-field">
          <label>Estado</label>
          <input type="text" id="odo_estado" readonly>
        </div>
      </form>
    </div>
    <div class="odo-modal__footer">
      <div class="odo-actions-left">
        <button type="button" class="odo-btn" id="odo-btn-save">Guardar</button>
        <button type="button" class="odo-btn" id="odo-btn-update" style="display:none;">Actualizar</button>
      </div>
      <div class="odo-actions-right">
        <button type="button" class="odo-btn odo-btn--primary" id="odo-btn-approve">Aprobar</button>
        <button type="button" class="odo-btn odo-btn--warn"    id="odo-btn-done">Realizada</button>
        <button type="button" class="odo-btn odo-btn--danger"  id="odo-btn-cancel">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<!-- JQuery (WP suele ya tenerlo; por si acaso lo cargamos de CDN sin bloquear el render) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" defer></script>
<!-- FullCalendar v6 (JS y CSS) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" />
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>

<script>
(function(){
  // Datos de AJAX y nonce embebidos desde PHP
  var ODO = {
    ajax: '<?php echo $ajax; ?>',
    nonce: '<?php echo $nonce; ?>',
    i18n: {
      create_title:'Nueva cita', edit_title:'Cita'
    }
  };

  // Esperar a que carguen los scripts defer
  function ready(cb){
    if(document.readyState === 'complete' || document.readyState === 'interactive'){ cb(); }
    else document.addEventListener('DOMContentLoaded', cb);
  }

  ready(function(){
    try {
      var calEl = document.getElementById('odo-calendar');
      if(!calEl){ console.error('ASETEC ODO LITE: no hay #odo-calendar'); return; }
      if(typeof FullCalendar === 'undefined'){ console.error('ASETEC ODO LITE: FullCalendar no cargó'); return; }

      function estadoColor(s){
        return {
          pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981',
          cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'
        }[s] || '#64748b';
      }
      function toLocalInput(dtStr){ if(!dtStr) return ''; return dtStr.replace(' ', 'T').slice(0,16); }
      function fromLocalInput(val){ if(!val) return ''; return val.replace('T',' ') + ':00'; }
      function openModal(title){ document.getElementById('odo-modal-title').textContent = title||''; document.getElementById('odo-modal').classList.add('is-open'); document.getElementById('odo-modal').setAttribute('aria-hidden','false'); }
      function closeModal(){ document.getElementById('odo-modal').classList.remove('is-open'); document.getElementById('odo-modal').setAttribute('aria-hidden','true'); var f=document.getElementById('odo-form'); if(f) f.reset(); document.getElementById('odo_post_id').value=''; document.getElementById('odo_estado').value=''; document.getElementById('odo-btn-save').style.display=''; document.getElementById('odo-btn-update').style.display='none'; }

      document.getElementById('odo-btn-close').addEventListener('click', closeModal);
      document.querySelector('.odo-modal__backdrop').addEventListener('click', closeModal);

      var cal = new FullCalendar.Calendar(calEl, {
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

        // Carga por rango visible
        datesSet: function(arg){
          var payload = new URLSearchParams();
          payload.set('action','asetec_odo_events');
          payload.set('nonce', ODO.nonce);
          payload.set('start', arg.startStr);
          payload.set('end', arg.endStr);

          fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
            .then(function(r){ return r.json(); })
            .then(function(r){
              if(!r || !r.success || !r.data || !Array.isArray(r.data.events)){ console.warn('ASETEC ODO LITE: eventos inválidos', r); return; }
              var evs = r.data.events.map(function(ev){
                var est = ev.extendedProps && ev.extendedProps.estado;
                if(est){ ev.backgroundColor = estadoColor(est); ev.borderColor = ev.backgroundColor; }
                ev.display='block';
                return ev;
              });
              cal.removeAllEvents();
              cal.addEventSource(evs);
            })
            .catch(function(e){ console.error('ASETEC ODO LITE: fallo eventos', e); });
        },

        select: function(sel){
          document.getElementById('odo_start').value = toLocalInput(sel.startStr.replace('Z','').replace('T',' '));
          document.getElementById('odo_end').value   = toLocalInput(sel.endStr  .replace('Z','').replace('T',' '));
          document.getElementById('odo_estado').value= 'pendiente';
          document.getElementById('odo-btn-save').style.display='';
          document.getElementById('odo-btn-update').style.display='none';
          openModal(ODO.i18n.create_title);
        },

        eventClick: function(info){
          var id = info.event.extendedProps && info.event.extendedProps.post_id;
          if(!id) return;
          var payload = new URLSearchParams();
          payload.set('action','asetec_odo_show');
          payload.set('nonce', ODO.nonce);
          payload.set('id', id);

          fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
            .then(function(r){ return r.json(); })
            .then(function(r){
              if(!r || !r.success){ alert((r && r.data && r.data.msg) || 'Error'); return; }
              var d = r.data || {};
              document.getElementById('odo_post_id').value = id;
              document.getElementById('odo_start').value   = toLocalInput((d.start||'').replace('T',' '));
              document.getElementById('odo_end').value     = toLocalInput((d.end  ||'').replace('T',' '));
              document.getElementById('odo_nombre').value  = d.nombre || '';
              document.getElementById('odo_cedula').value  = d.cedula || '';
              document.getElementById('odo_correo').value  = d.correo || '';
              document.getElementById('odo_telefono').value= d.telefono || '';
              document.getElementById('odo_estado').value  = d.estado || '';
              document.getElementById('odo-btn-save').style.display='none';
              document.getElementById('odo-btn-update').style.display='';
              openModal(ODO.i18n.edit_title);
            })
            .catch(function(e){ alert('Error al cargar la cita'); console.error('ASETEC ODO LITE: fallo show', e); });
        },

        eventDrop: function(info){
          var id = info.event.extendedProps.post_id;
          var start = info.event.startStr.replace('Z','');
          var end   = (info.event.endStr||'').replace('Z','');
          if(!id || !start){ info.revert(); return; }
          var payload = new URLSearchParams();
          payload.set('action','asetec_odo_reschedule');
          payload.set('nonce', ODO.nonce);
          payload.set('id', id);
          payload.set('start', start);
          payload.set('end', end);
          fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
            .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'No se pudo reprogramar'); info.revert(); return; } cal.refetchEvents(); })
            .catch(function(){ alert('Error al reprogramar'); info.revert(); });
        },

        eventResize: function(info){
          var id = info.event.extendedProps.post_id;
          var start = info.event.startStr.replace('Z','');
          var end   = info.event.endStr.replace('Z','');
          if(!id || !start || !end){ info.revert(); return; }
          var payload = new URLSearchParams();
          payload.set('action','asetec_odo_reschedule');
          payload.set('nonce', ODO.nonce);
          payload.set('id', id);
          payload.set('start', start);
          payload.set('end', end);
          fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
            .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'No se pudo ajustar'); info.revert(); return; } cal.refetchEvents(); })
            .catch(function(){ alert('Error al ajustar'); info.revert(); });
        }
      });

      cal.render();

      // Botones modal
      document.getElementById('odo-btn-save').addEventListener('click', function(){
        var payload = new URLSearchParams();
        payload.set('action','asetec_odo_create');
        payload.set('nonce', ODO.nonce);
        payload.set('start', fromLocalInput(document.getElementById('odo_start').value));
        payload.set('end',   fromLocalInput(document.getElementById('odo_end').value));
        payload.set('nombre', document.getElementById('odo_nombre').value);
        payload.set('cedula', document.getElementById('odo_cedula').value);
        payload.set('correo', document.getElementById('odo_correo').value);
        payload.set('telefono', document.getElementById('odo_telefono').value);
        fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
          .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'No se pudo crear'); return; } closeModal(); cal.refetchEvents(); })
          .catch(function(){ alert('Error al crear'); });
      });

      document.getElementById('odo-btn-update').addEventListener('click', function(){
        var id = document.getElementById('odo_post_id').value; if(!id) return;
        var payload = new URLSearchParams();
        payload.set('action','asetec_odo_reschedule');
        payload.set('nonce', ODO.nonce);
        payload.set('id', id);
        payload.set('start', fromLocalInput(document.getElementById('odo_start').value));
        payload.set('end',   fromLocalInput(document.getElementById('odo_end').value));
        fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
          .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'No se pudo actualizar'); return; } closeModal(); cal.refetchEvents(); })
          .catch(function(){ alert('Error al actualizar'); });
      });

      document.getElementById('odo-btn-approve').addEventListener('click', function(){
        var id = document.getElementById('odo_post_id').value; if(!id) return;
        var payload = new URLSearchParams();
        payload.set('action','asetec_odo_approve');
        payload.set('nonce', ODO.nonce);
        payload.set('id', id);
        fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
          .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'Error'); return; } closeModal(); cal.refetchEvents(); });
      });

      document.getElementById('odo-btn-cancel').addEventListener('click', function(){
        var id = document.getElementById('odo_post_id').value; if(!id) return;
        if(!confirm('¿Seguro que desea cancelar esta cita?')) return;
        var payload = new URLSearchParams();
        payload.set('action','asetec_odo_cancel');
        payload.set('nonce', ODO.nonce);
        payload.set('id', id);
        fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
          .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'Error'); return; } closeModal(); cal.refetchEvents(); });
      });

      document.getElementById('odo-btn-done').addEventListener('click', function(){
        var id = document.getElementById('odo_post_id').value; if(!id) return;
        var payload = new URLSearchParams();
        payload.set('action','asetec_odo_mark_done');
        payload.set('nonce', ODO.nonce);
        payload.set('id', id);
        fetch(ODO.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:payload })
          .then(r=>r.json()).then(function(r){ if(!r.success){ alert((r.data&&r.data.msg)||'Error'); return; } closeModal(); cal.refetchEvents(); });
      });

    } catch(e){
      console.error('ASETEC ODO LITE: excepción en init', e);
    }
  });
})();
</script>
<?php
        return ob_get_clean();
    }
}

} // class_exists
