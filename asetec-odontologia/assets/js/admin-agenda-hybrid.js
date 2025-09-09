(function($){
  function colorByEstado(s){
    return ({pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981', cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'}[s] || '#64748b');
  }
  function fmtDT(v){ if(!v) return ''; const d=new Date(v); d.setMinutes(d.getMinutes()-d.getTimezoneOffset()); return d.toISOString().slice(0,16); }
  function toast(msg){
    const el = document.getElementById('odo3-toast');
    if(!el) return;
    el.textContent = msg || '';
    el.classList.add('show');
    setTimeout(()=>el.classList.remove('show'), 1700);
  }

  $(function(){
    const calEl = document.getElementById('odo3-calendar');
    // Si falta el contenedor o FullCalendar, salimos sin romper
    if (!calEl || !window.FullCalendar) return;

    // --- Modal / form
    const modal    = document.getElementById('odo3-modal');
    const closeBtn = document.getElementById('odo3-close');
    const titleEl  = document.getElementById('odo3-modal-title');
    const backdrop =  modal ? modal.querySelector('.odo3-backdrop') : null;
if (backdrop) backdrop.addEventListener('click', closeModal);

    const F = {
      start:    document.getElementById('odo3-start'),
      end:      document.getElementById('odo3-end'),
      nombre:   document.getElementById('odo3-nombre'),
      cedula:   document.getElementById('odo3-cedula'),
      correo:   document.getElementById('odo3-correo'),
      telefono: document.getElementById('odo3-telefono'),
      estado:   document.getElementById('odo3-estado'),
    };

    let currentId = null;

    function openModal(){
      if(!modal) return;
      modal.classList.add('open');
      modal.setAttribute('aria-hidden','false');
    }
    function closeModal(){
      if(!modal) return;
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden','true');
      currentId=null;
    }

    if (closeBtn)  closeBtn.addEventListener('click', closeModal);
    if (backdrop)  backdrop.addEventListener('click', closeModal);

    // --- Toolbar / filtros
    const inputSearch = document.getElementById('odo3-search');
    const btnNew      = document.getElementById('odo3-new');
    const viewBtns    = document.querySelectorAll('.odo3-view');
    const chkFilters  = document.querySelectorAll('.odo3-filter');

    function getFilters(){
      const set = new Set();
      chkFilters.forEach(c=>{ if(c.checked) set.add(c.value); });
      return set;
    }

    // --- Calendario
    const calendar = new FullCalendar.Calendar(calEl, {
      initialView: 'timeGridWeek',
      slotMinTime: '07:00:00',
      slotMaxTime: '20:00:00',
      allDaySlot: false,
      nowIndicator: true,
      locale: 'es',
      headerToolbar: false,
      selectable: true,
      editable: true,
      eventOverlap: false,

      events: async (info, success, failure)=>{
        try{
          const body = new URLSearchParams({
            action:'asetec_odo_events',
            nonce: ASETEC_ODO_ADMIN3.nonce,
            start: info.startStr,
            end  : info.endStr
          });
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body
          });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error eventos');

          const q    = (inputSearch && inputSearch.value || '').toLowerCase();
          const filt = getFilters();

          const mapped = (j.data?.events || []).map(ev=>{
            const props  = ev.extendedProps || {};
            const estado = props.estado || '';
            // usar SIEMPRE post_id como id del evento (lo que espera el backend)
            const pid = String(props.post_id || ev.id || '');
            return {
              id: pid,
              title: ev.title || '',
              start: ev.start,
              end:   ev.end,
              extendedProps: props,
              backgroundColor: colorByEstado(estado),
              borderColor:     colorByEstado(estado)
            };
          }).filter(e=>{
            const estado = e.extendedProps?.estado || '';
            const okEstado = filt.has(estado);
            const text = (e.title||'').toLowerCase() + ' ' + (e.extendedProps?.cedula||'').toLowerCase();
            const okQ = !q || text.includes(q);
            return okEstado && okQ;
          });

          success(mapped);
        }catch(err){ console.error(err); failure(err); }
      },

      select: (selInfo)=>{
        currentId = null;
        if (titleEl) titleEl.textContent = 'Nueva cita';
        if (F.start) F.start.value = fmtDT(selInfo.start);
        if (F.end)   F.end.value   = fmtDT(selInfo.end);
        if (F.nombre)   F.nombre.value='';
        if (F.cedula)   F.cedula.value='';
        if (F.correo)   F.correo.value='';
        if (F.telefono) F.telefono.value='';
        if (F.estado)   F.estado.value='pendiente';
        openModal();
      },

      eventClick: async (arg)=>{
        try{
          const id = arg.event.id; // ya es post_id por nuestro map()
          if(!id){ toast('ID no válido'); return; }
          currentId = id;
          if (titleEl) titleEl.textContent = 'Editar cita';

          const body = new URLSearchParams({ action:'asetec_odo_show', nonce:ASETEC_ODO_ADMIN3.nonce, id });
          const res  = await fetch(ASETEC_ODO_ADMIN3.ajax,{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body
          });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error');

          const d = j.data || {};
          if (F.start)    F.start.value    = fmtDT(d.start || arg.event.start);
          if (F.end)      F.end.value      = fmtDT(d.end   || arg.event.end);
          if (F.nombre)   F.nombre.value   = d.paciente_nombre || arg.event.title || '';
          if (F.cedula)   F.cedula.value   = d.paciente_cedula || '';
          if (F.correo)   F.correo.value   = d.paciente_correo || '';
          if (F.telefono) F.telefono.value = d.paciente_telefono || '';
          if (F.estado)   F.estado.value   = d.estado || arg.event.extendedProps?.estado || 'pendiente';

          openModal();
        }catch(e){ console.error(e); toast('No se pudo cargar la cita'); }
      },

      eventDrop: async (arg)=>{
        try{
          const body = new URLSearchParams({
            action:'asetec_odo_reschedule',
            nonce: ASETEC_ODO_ADMIN3.nonce,
            id: arg.event.id,
            start: arg.event.start.toISOString(),
            end:   arg.event.end ? arg.event.end.toISOString() : ''
          });
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error reprogramar');
          toast('Cita reprogramada');
        }catch(e){ console.error(e); arg.revert(); toast('No se pudo reprogramar'); }
      },

      eventResize: async (arg)=>{
        try{
          const body = new URLSearchParams({
            action:'asetec_odo_reschedule',
            nonce: ASETEC_ODO_ADMIN3.nonce,
            id: arg.event.id,
            start: arg.event.start.toISOString(),
            end:   arg.event.end ? arg.event.end.toISOString() : ''
          });
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error actualizar');
          toast('Cita actualizada');
        }catch(e){ console.error(e); arg.revert(); toast('No se pudo actualizar'); }
      }
    });

    calendar.render();

    // Cambiar vista (Mes / Semana / Día)
    if (viewBtns && viewBtns.length){
      viewBtns.forEach(btn=>{
        btn.addEventListener('click', ()=>{
          viewBtns.forEach(b=>b.classList.remove('is-active'));
          btn.classList.add('is-active');
          calendar.changeView(btn.getAttribute('data-view'));
        });
      });
    }

    // Filtros / búsqueda
    if (inputSearch) inputSearch.addEventListener('input', ()=> calendar.refetchEvents());
    chkFilters.forEach(chk=> chk.addEventListener('change', ()=> calendar.refetchEvents()));

    // Nueva cita
    if (btnNew){
      btnNew.addEventListener('click', ()=>{
        currentId = null;
        if (titleEl) titleEl.textContent = 'Nueva cita';
        const now = new Date(); const end = new Date(now.getTime()+40*60000);
        if (F.start) F.start.value = fmtDT(now);
        if (F.end)   F.end.value   = fmtDT(end);
        if (F.nombre)   F.nombre.value='';
        if (F.cedula)   F.cedula.value='';
        if (F.correo)   F.correo.value='';
        if (F.telefono) F.telefono.value='';
        if (F.estado)   F.estado.value='pendiente';
        openModal();
      });
    }

    // Acciones modal
    const btnSave   = document.getElementById('odo3-save');
    const btnUpdate = document.getElementById('odo3-update');
    const btnApprove= document.getElementById('odo3-approve');
    const btnDone   = document.getElementById('odo3-done');
    const btnCancel = document.getElementById('odo3-cancel');

    if (btnSave) btnSave.addEventListener('click', async ()=>{
      try{
        const body = new URLSearchParams({
          action:'asetec_odo_create',
          nonce: ASETEC_ODO_ADMIN3.nonce,
          start: new Date(F.start.value).toISOString(),
          end:   new Date(F.end.value).toISOString(),
          nombre: F.nombre.value, cedula: F.cedula.value,
          correo: F.correo.value, telefono: F.telefono.value,
          estado: F.estado.value || 'pendiente'
        });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error crear');
        toast('Cita creada'); closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo crear'); }
    });

    if (btnUpdate) btnUpdate.addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({
          action:'asetec_odo_update',
          nonce: ASETEC_ODO_ADMIN3.nonce,
          id: currentId,
          start: new Date(F.start.value).toISOString(),
          end:   new Date(F.end.value).toISOString(),
          nombre: F.nombre.value, cedula: F.cedula.value,
          correo: F.correo.value, telefono: F.telefono.value,
          estado: F.estado.value
        });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error actualizar');
        toast('Cita actualizada'); closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo actualizar'); }
    });

    if (btnApprove) btnApprove.addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error aprobar');
        toast('Cita aprobada'); closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo aprobar'); }
    });

    if (btnDone) btnDone.addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error marcar realizada');
        toast('Marcada como realizada'); closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo marcar'); }
    });

    if (btnCancel) btnCancel.addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error cancelar');
        toast('Cita cancelada'); closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo cancelar'); }
    });

  });
})(jQuery);
