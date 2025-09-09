(function($){
  function colorByEstado(s){
    return ({pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981', cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'}[s] || '#64748b');
  }
  function fmtDT(v){ if(!v) return ''; const d=new Date(v); d.setMinutes(d.getMinutes()-d.getTimezoneOffset()); return d.toISOString().slice(0,16); }
  function toast(msg){ const el = document.getElementById('odo3-toast'); el.textContent = msg || ''; el.style.opacity = 1; el.style.transform = 'translateY(0)'; setTimeout(()=>{ el.style.opacity=0; el.style.transform='translateY(10px)'; }, 1700); }

  $(function(){
    const calEl    = document.getElementById('odo3-calendar');
    if(!calEl || !window.FullCalendar) return;

    const modal    = document.getElementById('odo3-modal');
    const closeBtn = document.getElementById('odo3-close');
    const titleEl  = document.getElementById('odo3-modal-title');

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

    function openModal(){ modal.style.display='block'; }
    function closeModal(){ modal.style.display='none'; currentId=null; }
    closeBtn.addEventListener('click', closeModal);
    modal.querySelector('.backdrop').addEventListener('click', closeModal);

    // Toolbar
    const inputSearch = document.getElementById('odo3-search');
    const btnNew      = document.getElementById('odo3-new');
    const chkFilters  = document.querySelectorAll('.odo3-filter');

    function getFilters(){
      const set = new Set();
      chkFilters.forEach(c=>{ if(c.checked) set.add(c.value); });
      return set;
    }

    // Calendario (FullCalendar)
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
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error eventos');
          const q = (inputSearch.value || '').toLowerCase();
          const filt = getFilters();
          const mapped = (j.data?.events || []).map(ev=>{
            const estado = ev.extendedProps?.estado || '';
            return {
              id: String(ev.id || ev.extendedProps?.post_id || ''),
              title: ev.title || '',
              start: ev.start, end: ev.end,
              extendedProps: Object.assign({}, ev.extendedProps||{}),
              backgroundColor: colorByEstado(estado),
              borderColor: colorByEstado(estado)
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
        // Crear cita: prellenar y abrir modal
        currentId = null;
        titleEl.textContent = 'Nueva cita';
        F.start.value = fmtDT(selInfo.start);
        F.end.value   = fmtDT(selInfo.end);
        F.nombre.value=''; F.cedula.value=''; F.correo.value=''; F.telefono.value='';
        F.estado.value='pendiente';
        openModal();
      },

      eventClick: async (arg)=>{
        try{
          const id = arg.event.id;
          currentId = id;
          titleEl.textContent = 'Editar cita';
          // cargar detalle
          const body = new URLSearchParams({ action:'asetec_odo_show', nonce:ASETEC_ODO_ADMIN3.nonce, id });
          const res  = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j    = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error');

          const d = j.data || {};
          F.start.value = fmtDT(d.start || arg.event.start);
          F.end.value   = fmtDT(d.end   || arg.event.end);
          F.nombre.value   = d.paciente_nombre || arg.event.title || '';
          F.cedula.value   = d.paciente_cedula || '';
          F.correo.value   = d.paciente_correo || '';
          F.telefono.value = d.paciente_telefono || '';
          F.estado.value   = d.estado || arg.event.extendedProps?.estado || 'pendiente';
          openModal();
        }catch(e){ console.error(e); toast('No se pudo cargar la cita'); }
      },

      eventDrop: async (arg)=>{
        // Reprogramación por drag & drop
        try{
          const body = new URLSearchParams({
            action:'asetec_odo_reschedule',
            nonce: ASETEC_ODO_ADMIN3.nonce,
            id: arg.event.id,
            start: arg.event.start.toISOString(),
            end: arg.event.end ? arg.event.end.toISOString() : ''
          });
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error reprogramar');
          toast('Cita reprogramada');
        }catch(e){
          console.error(e); arg.revert(); toast('No se pudo reprogramar');
        }
      },

      eventResize: async (arg)=>{
        // Reprogramación por resize
        try{
          const body = new URLSearchParams({
            action:'asetec_odo_reschedule',
            nonce: ASETEC_ODO_ADMIN3.nonce,
            id: arg.event.id,
            start: arg.event.start.toISOString(),
            end: arg.event.end ? arg.event.end.toISOString() : ''
          });
          const res = await fetch(ASETEC_ODO_ADMIN3.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
          const j = await res.json();
          if(!j.success) throw new Error(j?.data?.msg || 'Error reprogramar');
          toast('Cita actualizada');
        }catch(e){
          console.error(e); arg.revert(); toast('No se pudo actualizar');
        }
      }
    });

    calendar.render();

    // Filtros y búsqueda
    inputSearch.addEventListener('input', ()=> calendar.refetchEvents());
    chkFilters.forEach(chk=> chk.addEventListener('change', ()=> calendar.refetchEvents()));

    // Nueva cita (botón)
    btnNew.addEventListener('click', ()=>{
      currentId = null;
      titleEl.textContent = 'Nueva cita';
      const now = new Date(); const end = new Date(now.getTime()+40*60000);
      F.start.value = fmtDT(now); F.end.value = fmtDT(end);
      F.nombre.value=''; F.cedula.value=''; F.correo.value=''; F.telefono.value='';
      F.estado.value='pendiente';
      openModal();
    });

    // Acciones modal
    document.getElementById('odo3-save').addEventListener('click', async ()=>{
      // Crear
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
        toast('Cita creada');
        closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo crear'); }
    });

    document.getElementById('odo3-update').addEventListener('click', async ()=>{
      // Actualizar datos básicos + reprogramar si cambió fecha
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
        toast('Cita actualizada');
        closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo actualizar'); }
    });

    document.getElementById('odo3-approve').addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error aprobar');
        toast('Cita aprobada');
        closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo aprobar'); }
    });

    document.getElementById('odo3-done').addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error marcar realizada');
        toast('Marcada como realizada');
        closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo marcar'); }
    });

    document.getElementById('odo3-cancel').addEventListener('click', async ()=>{
      if(!currentId) return;
      try{
        const body = new URLSearchParams({ action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN3.nonce, id: currentId });
        const res = await fetch(ASETEC_ODO_ADMIN3.ajax,{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
        const j = await res.json();
        if(!j.success) throw new Error(j?.data?.msg || 'Error cancelar');
        toast('Cita cancelada');
        closeModal(); calendar.refetchEvents();
      }catch(e){ console.error(e); toast('No se pudo cancelar'); }
    });

  });
})(jQuery);
