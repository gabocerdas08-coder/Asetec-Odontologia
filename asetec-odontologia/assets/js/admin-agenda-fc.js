(function(){
  // Helpers
  const $$ = (root, sel) => root.querySelector(sel);
  const h  = (html) => { const t=document.createElement('template'); t.innerHTML=html.trim(); return t.content.firstElementChild; };
  const qs = (o) => new URLSearchParams(o).toString();

  const estadoColor = (s)=>({
    pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981',
    cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'
  }[s] || '#64748b');

  const ESTADOS = ['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada'];

  class AsetecAgendaFC extends HTMLElement{
    constructor(){
      super();
      this.attachShadow({mode:'open'});
      this.state = {
        search: '',
        filters: new Set(ESTADOS),
        calendar: null,
      };
    }

    connectedCallback(){
      const sh = this.shadowRoot;

      // estilos base (UI/UX)
      sh.appendChild(h(`<style>
        :host{display:block}
        .toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0 14px}
        .legend{display:flex;gap:12px;flex-wrap:wrap;font-size:12px;color:#374151}
        .dot{width:10px;height:10px;border-radius:999px;display:inline-block;margin-right:6px}
        .controls{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .search{min-width:260px;border:1px solid #d1d5db;border-radius:10px;padding:9px 12px}
        .btn{background:#111827;color:#fff;border:0;border-radius:10px;padding:9px 12px;cursor:pointer}
        .btn:hover{background:#0b1324}
        .filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
        .filters label{display:inline-flex;gap:6px;align-items:center;background:#f3f4f6;padding:6px 10px;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer}
        .calwrap{min-height:640px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}

        /* Modal */
        .modal{position:fixed;inset:0;display:none;z-index:99999}
        .modal.open{display:block}
        .backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45)}
        .dialog{position:relative;max-width:780px;margin:6vh auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.25)}
        .header{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid #e5e7eb}
        .body{padding:16px 18px}
        .footer{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:12px 18px;border-top:1px solid #e5e7eb;background:#fafafa}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media (max-width:720px){ .grid{grid-template-columns:1fr;} }
        .field label{display:block;font-size:12px;color:#374151;margin:0 0 4px}
        .field input, .field select, .field textarea{width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px}
        .actionsL,.actionsR{display:flex;gap:8px;flex-wrap:wrap}
        .btnP{background:#1d4ed8}.btnP:hover{background:#1e40af}
        .btnW{background:#059669}.btnW:hover{background:#047857}
        .btnD{background:#b91c1c}.btnD:hover{background:#991b1b}
        .toast{position:fixed;right:14px;bottom:14px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;font-size:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(10px);transition:all .2s;z-index:999999}
        .toast.show{opacity:1;transform:translateY(0)}
      </style>`));

      // toolbar superior
      const toolbar = h(`
        <div class="toolbar">
          <div class="legend">
            <span><i class="dot" style="background:#f59e0b"></i> Pendiente</span>
            <span><i class="dot" style="background:#3b82f6"></i> Aprobada</span>
            <span><i class="dot" style="background:#10b981"></i> Realizada</span>
            <span><i class="dot" style="background:#ef4444"></i> Cancelada</span>
            <span><i class="dot" style="background:#8b5cf6"></i> Reprogramada</span>
          </div>
          <div class="controls">
            <input class="search" id="s" type="search" placeholder="${ASETEC_ODO_ADMIN2?.labels?.search || 'Buscar…'}">
            <button class="btn" id="new">${ASETEC_ODO_ADMIN2?.labels?.new || 'Nueva cita'}</button>
          </div>
        </div>
      `);
      sh.appendChild(toolbar);

      // filtros por estado
      const filters = h(`<div class="filters"></div>`);
      filters.innerHTML = ESTADOS.map(e=>`<label><input type="checkbox" value="${e}" checked> ${e.replace('_',' ')}</label>`).join('');
      sh.appendChild(filters);

      // calendario
      const wrap = h(`<div class="calwrap"><div id="cal"></div></div>`);
      sh.appendChild(wrap);
      const calEl = $$(wrap, '#cal');

      // modal
      const modal = h(`
        <div class="modal" id="modal">
          <div class="backdrop" data-close></div>
          <div class="dialog">
            <div class="header">
              <strong id="mtitle">Cita</strong>
              <button class="btn" data-close>${ASETEC_ODO_ADMIN2?.labels?.close || 'Cerrar'}</button>
            </div>
            <div class="body">
              <div class="grid">
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.start || 'Inicio'}</label><input type="datetime-local" id="mstart"/></div>
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.end || 'Fin'}</label><input type="datetime-local" id="mend"/></div>
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.name || 'Nombre'}</label><input type="text" id="mname"/></div>
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.id || 'Cédula'}</label><input type="text" id="mid"/></div>
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.email || 'Correo'}</label><input type="email" id="memail"/></div>
                <div class="field"><label>${ASETEC_ODO_ADMIN2?.labels?.phone || 'Teléfono'}</label><input type="text" id="mphone"/></div>
              </div>
            </div>
            <div class="footer">
              <div class="actionsL">
                <button class="btn btnP" id="msave">${ASETEC_ODO_ADMIN2?.labels?.save || 'Guardar'}</button>
                <button class="btn" id="mupdate" style="display:none">${ASETEC_ODO_ADMIN2?.labels?.update || 'Actualizar'}</button>
              </div>
              <div class="actionsR">
                <button class="btn btnW" id="mapprove" style="display:none">${ASETEC_ODO_ADMIN2?.labels?.approve || 'Aprobar'}</button>
                <button class="btn btnW" id="mdone" style="display:none">${ASETEC_ODO_ADMIN2?.labels?.mark_done || 'Realizada'}</button>
                <button class="btn btnD" id="mcancel" style="display:none">${ASETEC_ODO_ADMIN2?.labels?.cancel || 'Cancelar'}</button>
              </div>
            </div>
          </div>
        </div>
      `);
      sh.appendChild(modal);

      const toast = h(`<div class="toast"></div>`); sh.appendChild(toast);
      const showToast = (msg)=>{ toast.textContent = msg||''; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1600); };

      // utils modal
      const openModal = ()=> modal.classList.add('open');
      const closeModal = ()=> modal.classList.remove('open');
      modal.addEventListener('click', e=>{ if (e.target.matches('[data-close]')) closeModal(); });

      // refs modal
      const m = {
        title: $$(modal,'#mtitle'),
        start: $$(modal,'#mstart'),
        end  : $$(modal,'#mend'),
        name : $$(modal,'#mname'),
        id   : $$(modal,'#mid'),
        email: $$(modal,'#memail'),
        phone: $$(modal,'#mphone'),
        save : $$(modal,'#msave'),
        update:$$(modal,'#mupdate'),
        approve:$$(modal,'#mapprove'),
        done : $$(modal,'#mdone'),
        cancel:$$(modal,'#mcancel'),
      };

      // helpers fechas
      const pad = (n)=> (n<10?'0':'')+n;
      const toLocal = (d)=>{
        const x = new Date(d);
        const y = x.getFullYear(), mo=pad(x.getMonth()+1), da=pad(x.getDate());
        const hh=pad(x.getHours()), mm=pad(x.getMinutes());
        return `${y}-${mo}-${da}T${hh}:${mm}`;
      };

      // FullCalendar
      const calendar = new FullCalendar.Calendar(calEl, {
        initialView: 'timeGridWeek',
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        nowIndicator: true,
        locale: 'es',
        headerToolbar: false, // usamos nuestra toolbar
        selectable: true,
        editable: true,
        eventOverlap: false,
        events: async (info, success, failure)=>{
          try{
            const body = qs({ action:'asetec_odo_events', nonce:ASETEC_ODO_ADMIN2.nonce, start:info.startStr, end:info.endStr });
            const r = await fetch(ASETEC_ODO_ADMIN2.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
            const j = await r.json();
            if(!j.success) throw new Error(j?.data?.msg||'Error eventos');
            const list = (j.data?.events||[]).map(ev=>{
              const est = ev.extendedProps?.estado;
              return {
                id: String(ev.id || ev.extendedProps?.post_id || ''),
                title: ev.title || '',
                start: ev.start, end: ev.end,
                extendedProps: ev.extendedProps || {},
                backgroundColor: estadoColor(est),
                borderColor: estadoColor(est)
              };
            });
            success(list);
          }catch(e){ failure(e); }
        },
        select: (sel)=>{
          // Nueva cita
          m.title.textContent = 'Nueva cita';
          m.save.style.display = '';
          m.update.style.display = 'none';
          m.approve.style.display = 'none';
          m.done.style.display = 'none';
          m.cancel.style.display = 'none';
          m.start.value = toLocal(sel.start);
          m.end.value   = toLocal(sel.end);
          m.name.value = m.id.value = m.email.value = m.phone.value = '';
          m.save.onclick = async ()=>{
            const payload = {
              action:'asetec_odo_create', nonce:ASETEC_ODO_ADMIN2.nonce,
              start: new Date(m.start.value).toISOString(),
              end  : new Date(m.end.value).toISOString(),
              nombre: m.name.value, cedula:m.id.value, correo:m.email.value, telefono:m.phone.value
            };
            const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:qs(payload)});
            const j = await r.json();
            if(!j.success){ alert(j?.data?.msg||'Error guardando'); return; }
            closeModal(); calendar.refetchEvents(); showToast('Cita creada');
          };
          openModal();
        },
        eventClick: async (arg)=>{
          try{
            const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body:qs({action:'asetec_odo_show', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id})});
            const j = await r.json();
            if(!j.success) throw new Error(j?.data?.msg||'Error show');

            const d = j.data || {};
            m.title.textContent = `Cita`;
            m.start.value = toLocal(d.start || arg.event.start);
            m.end.value   = toLocal(d.end   || arg.event.end);
            m.name.value  = d.paciente_nombre || '';
            m.id.value    = d.paciente_cedula || '';
            m.email.value = d.paciente_correo || '';
            m.phone.value = d.paciente_telefono || '';

            m.save.style.display   = 'none';
            m.update.style.display = '';
            m.approve.style.display= '';
            m.done.style.display   = '';
            m.cancel.style.display = '';

            m.update.onclick = async ()=>{
              const payload = {
                action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id,
                start:new Date(m.start.value).toISOString(), end:new Date(m.end.value).toISOString()
              };
              const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:qs(payload)});
              const jj= await r.json();
              if(!jj.success){ alert(jj?.data?.msg||'Error reprogramar'); return; }
              closeModal(); calendar.refetchEvents(); showToast('Actualizado');
            };
            m.approve.onclick = async ()=>{
              const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:qs({action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id})});
              const jj= await r.json();
              if(!jj.success){ alert(jj?.data?.msg||'Error aprobar'); return; }
              closeModal(); calendar.refetchEvents(); showToast('Aprobada');
            };
            m.done.onclick = async ()=>{
              const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:qs({action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id})});
              const jj= await r.json();
              if(!jj.success){ alert(jj?.data?.msg||'Error'); return; }
              closeModal(); calendar.refetchEvents(); showToast('Realizada');
            };
            m.cancel.onclick = async ()=>{
              const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:qs({action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id})});
              const jj= await r.json();
              if(!jj.success){ alert(jj?.data?.msg||'Error cancelar'); return; }
              closeModal(); calendar.refetchEvents(); showToast('Cancelada');
            };

            openModal();
          }catch(e){ console.error(e); }
        },
        eventDrop: async (arg)=>{
          try{
            const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body:qs({action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id,
                       start:arg.event.start.toISOString(), end:arg.event.end.toISOString() })});
            const j = await r.json(); if(!j.success) throw new Error(j?.data?.msg||'Error');
            showToast('Reprogramada'); calendar.refetchEvents();
          }catch(e){ console.error(e); arg.revert(); }
        },
        eventResize: async (arg)=>{
          try{
            const r = await fetch(ASETEC_ODO_ADMIN2.ajax,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
              body:qs({action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN2.nonce, id:arg.event.id,
                       start:arg.event.start.toISOString(), end:arg.event.end.toISOString() })});
            const j = await r.json(); if(!j.success) throw new Error(j?.data?.msg||'Error');
            showToast('Duración actualizada'); calendar.refetchEvents();
          }catch(e){ console.error(e); arg.revert(); }
        }
      });

      calendar.render();
      this.state.calendar = calendar;

      // búsqueda
      $$(toolbar,'#s').addEventListener('input', (e)=>{
        this.state.search = (e.target.value || '').toLowerCase();
        this.applyFilters();
      });

      // filtros
      filters.addEventListener('change', ()=>{
        const checks = [...filters.querySelectorAll('input[type=checkbox]')];
        this.state.filters = new Set(checks.filter(c=>c.checked).map(c=>c.value));
        this.applyFilters();
      });

      // nueva cita sin seleccionar en el grid (abre modal vacío con ahora + 40 min)
      $$(toolbar,'#new').addEventListener('click', ()=>{
        const now = new Date(); const end = new Date(now.getTime()+ 40*60000);
        calendar.unselect();
        calendar.select(now, end);
      });

      // cerrar modal con botón
      // (ya está arriba con data-close)

      // método para aplicar filtros y búsqueda (sin pedir de nuevo al servidor)
      this.applyFilters = ()=>{
        const q = this.state.search;
        const allowed = this.state.filters;
        calendar.getEvents().forEach(ev=>{
          const est = ev.extendedProps?.estado || '';
          const text = (ev.title||'') + ' ' + (ev.extendedProps?.cedula||'');
          const match = (!q || text.toLowerCase().includes(q)) && allowed.has(est);
          ev.setProp('display', match ? 'auto' : 'none');
        });
      };
    }
  }

  customElements.define('asetec-odo-agenda-fc', AsetecAgendaFC);
})();
