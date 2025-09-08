(function(){
  // Helper para cargar CSS/JS externos en el shadow root
  function injectLink(sh, href){
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    sh.appendChild(link);
  }
  function injectScript(src){
    return new Promise((resolve, reject)=>{
      if (document.querySelector('script[src="'+src+'"]')) return resolve();
      const s = document.createElement('script');
      s.src = src; s.async = true;
      s.onload = ()=> resolve();
      s.onerror = ()=> reject(new Error('Fail load '+src));
      document.head.appendChild(s);
    });
  }

  class AsetecAgenda extends HTMLElement {
    constructor(){
      super();
      this.attachShadow({mode:'open'});
      this.state = {
        search: '',
        statusVisible: new Set(['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada']),
        calendar: null
      };
    }

    connectedCallback(){
      const sh = this.shadowRoot;

      // Estilos propios (aislados)
      const style = document.createElement('style');
      style.textContent = `
        :host{display:block}
        .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:10px 0 12px}
        .legend{display:flex;gap:10px;font-size:12px;color:#374151;flex-wrap:wrap}
        .dot{width:10px;height:10px;border-radius:999px;display:inline-block;margin-right:6px;vertical-align:middle}
        .controls{margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .search{min-width:240px;border:1px solid #d1d5db;border-radius:10px;padding:8px 10px}
        .btn{background:#1f2937;color:#fff;border:0;border-radius:10px;padding:8px 12px;cursor:pointer}
        .btn:hover{background:#111827}
        .filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
        .filters label{display:inline-flex;gap:6px;align-items:center;background:#f3f4f6;padding:6px 8px;border-radius:10px;border:1px solid #e5e7eb;cursor:pointer}
        .calwrap{min-height:640px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden}

        /* Modal en shadow */
        .modal{position:fixed;inset:0;z-index:99999;display:none}
        .modal.open{display:block}
        .backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45)}
        .dialog{position:relative;max-width:760px;margin:6vh auto;background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden}
        .header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #e5e7eb}
        .body{padding:16px 18px}
        .footer{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;border-top:1px solid #e5e7eb;background:#fafafa}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        @media (max-width:720px){ .grid{grid-template-columns:1fr;} }
        .field label{display:block;font-size:12px;color:#374151;margin:0 0 4px}
        .field input, .field select, .field textarea{
          width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px
        }
        .actionsL, .actionsR {display:flex;gap:8px;flex-wrap:wrap}
        .btnP{background:#1d4ed8} .btnP:hover{background:#1e40af}
        .btnW{background:#059669} .btnW:hover{background:#047857}
        .btnD{background:#b91c1c} .btnD:hover{background:#991b1b}

        .toast{position:fixed;right:14px;bottom:14px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;font-size:12px;box-shadow:0 10px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(10px);transition:all .2s;z-index:999999}
        .toast.show{opacity:1;transform:translateY(0)}
      `;
      sh.appendChild(style);

      // CSS de TUI en el Shadow (aislado)
      injectLink(sh, 'https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css');

      // UI wrapper
      const toolbar = document.createElement('div');
      toolbar.className = 'toolbar';
      toolbar.innerHTML = `
        <div class="legend">
          <span><i class="dot" style="background:#f59e0b"></i> Pendiente</span>
          <span><i class="dot" style="background:#3b82f6"></i> Aprobada</span>
          <span><i class="dot" style="background:#10b981"></i> Realizada</span>
          <span><i class="dot" style="background:#ef4444"></i> Cancelada</span>
          <span><i class="dot" style="background:#8b5cf6"></i> Reprogramada</span>
        </div>
        <div class="controls">
          <input class="search" id="odo2-search" type="search" placeholder="${(window.ASETEC_ODO_ADMIN2 && ASETEC_ODO_ADMIN2.labels && ASETEC_ODO_ADMIN2.labels.search) || 'Buscar…'}" />
          <button class="btn" id="odo2-new">${(ASETEC_ODO_ADMIN2?.labels?.new)||'Nueva cita'}</button>
        </div>
      `;
      sh.appendChild(toolbar);

      const filters = document.createElement('div');
      filters.className = 'filters';
      const estados = ['pendiente','aprobada','realizada','cancelada_usuario','cancelada_admin','reprogramada'];
      filters.innerHTML = estados.map(e=>{
        const lbl = e.replace('_',' ');
        return `<label><input type="checkbox" class="odo2-filter" value="${e}" checked> ${lbl}</label>`;
      }).join('');
      sh.appendChild(filters);

      const calWrap = document.createElement('div');
      calWrap.className = 'calwrap';
      const calEl = document.createElement('div');
      calWrap.appendChild(calEl);
      sh.appendChild(calWrap);

      // Modal
      const modal = document.createElement('div');
      modal.className = 'modal';
      modal.innerHTML = `
        <div class="backdrop"></div>
        <div class="dialog" role="dialog" aria-modal="true">
          <div class="header">
            <h3 id="odo2-title">${(ASETEC_ODO_ADMIN2?.labels?.title)||'Agenda'}</h3>
            <button class="btn" id="odo2-close">${(ASETEC_ODO_ADMIN2?.labels?.close)||'Cerrar'}</button>
          </div>
          <div class="body">
            <form id="odo2-form">
              <input type="hidden" id="odo2_post">
              <div class="grid">
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.start)||'Inicio'}</label>
                  <input type="datetime-local" id="odo2_start" step="600" required>
                </div>
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.end)||'Fin'}</label>
                  <input type="datetime-local" id="odo2_end" step="600" required>
                </div>
              </div>
              <div class="grid">
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.duration)||'Duración (min)'}</label>
                  <select id="odo2_dur">
                    <option value="20">20</option><option value="30">30</option>
                    <option value="40" selected>40</option><option value="60">60</option>
                  </select>
                </div>
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.status)||'Estado'}</label>
                  <input type="text" id="odo2_status" readonly>
                </div>
              </div>
              <div class="grid">
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.name)||'Nombre completo'}</label>
                  <input type="text" id="odo2_name" required>
                </div>
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.id)||'Cédula'}</label>
                  <input type="text" id="odo2_cid" required>
                </div>
              </div>
              <div class="grid">
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.email)||'Correo'}</label>
                  <input type="email" id="odo2_email" required>
                </div>
                <div class="field">
                  <label>${(ASETEC_ODO_ADMIN2?.labels?.phone)||'Teléfono'}</label>
                  <input type="tel" id="odo2_phone" required>
                </div>
              </div>
            </form>
          </div>
          <div class="footer">
            <div class="actionsL">
              <button class="btn"   id="odo2-save">${(ASETEC_ODO_ADMIN2?.labels?.save)||'Guardar'}</button>
              <button class="btn"   id="odo2-update" style="display:none">${(ASETEC_ODO_ADMIN2?.labels?.update)||'Actualizar'}</button>
            </div>
            <div class="actionsR">
              <button class="btn btnP" id="odo2-approve">${(ASETEC_ODO_ADMIN2?.labels?.approve)||'Aprobar'}</button>
              <button class="btn btnW" id="odo2-done">${(ASETEC_ODO_ADMIN2?.labels?.done)||'Realizada'}</button>
              <button class="btn btnD" id="odo2-cancel">${(ASETEC_ODO_ADMIN2?.labels?.cancel)||'Cancelar'}</button>
            </div>
          </div>
        </div>
      `;
      sh.appendChild(modal);

      const toast = document.createElement('div');
      toast.className = 'toast';
      sh.appendChild(toast);
      const showToast = (msg)=>{ toast.textContent = msg||''; toast.classList.add('show'); setTimeout(()=>toast.classList.remove('show'), 1800); };

      // Utilidades tiempo
      const toLocalInput = (s)=> s ? s.replace(' ', 'T').slice(0,16) : '';
      const fromLocalInput = (s)=> s ? s.replace('T',' ') + ':00' : '';
      const addMinutes = (d, m)=> { const x=new Date(d); x.setMinutes(x.getMinutes()+m); return x; };

      // Cargar TUI JS si hiciera falta
      const TUI_SRC = 'https://uicdn.toast.com/calendar/latest/toastui-calendar.min.js';
      injectScript(TUI_SRC).then(()=> this.initCalendar(calEl, sh, {modal, showToast}))
      .catch(err=>{
        calEl.innerHTML = '<p style="padding:12px;color:#b91c1c">No se pudo cargar el calendario.</p>';
        console.error(err);
      });

      // Toolbar handlers
      sh.getElementById('odo2-search').addEventListener('input', (e)=>{
        this.state.search = (e.target.value||'').toLowerCase().trim();
        if (this.state.calendar) this.state.calendar.render(true);
      });
      sh.getElementById('odo2-new').addEventListener('click', ()=>{
        this.openCreateModal(sh);
      });

      // Filters
      sh.querySelectorAll('.odo2-filter').forEach(ch=>{
        ch.addEventListener('change', ()=>{
          const v = ch.value;
          if (ch.checked) this.state.statusVisible.add(v);
          else this.state.statusVisible.delete(v);
          if (this.state.calendar) this.state.calendar.render(true);
        });
      });

      // Modal actions
      sh.getElementById('odo2-close').addEventListener('click', ()=> modal.classList.remove('open'));
      sh.querySelector('.backdrop').addEventListener('click', ()=> modal.classList.remove('open'));
      sh.getElementById('odo2_dur').addEventListener('change', ()=>{
        const s = sh.getElementById('odo2_start').value;
        if(!s) return;
        const dur = parseInt(sh.getElementById('odo2_dur').value,10)||40;
        const end = addMinutes(new Date(s.replace('T',' ')), dur);
        sh.getElementById('odo2_end').value = toLocalInput(end.toISOString().slice(0,19).replace('T',' '));
      });

      // Crear
      sh.getElementById('odo2-save').addEventListener('click', ()=>{
        const payload = {
          action: 'asetec_odo_create',
          nonce : window.ASETEC_ODO_ADMIN2?.nonce,
          start : fromLocalInput(sh.getElementById('odo2_start').value),
          end   : fromLocalInput(sh.getElementById('odo2_end').value),
          nombre: sh.getElementById('odo2_name').value,
          cedula: sh.getElementById('odo2_cid').value,
          correo: sh.getElementById('odo2_email').value,
          telefono: sh.getElementById('odo2_phone').value
        };
        fetch(window.ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload) })
        .then(r=>r.json()).then(r=>{
          if(!r.success) throw new Error(r?.data?.msg || 'Error al crear');
          modal.classList.remove('open'); showToast('Cita creada');
          this.reloadRange();
        }).catch(e=> alert(e.message));
      });

      // Actualizar (reprogramar)
      sh.getElementById('odo2-update').addEventListener('click', ()=>{
        const id = sh.getElementById('odo2_post').value;
        if(!id) return;
        const payload = {
          action:'asetec_odo_reschedule',
          nonce : window.ASETEC_ODO_ADMIN2?.nonce,
          id    : id,
          start : fromLocalInput(sh.getElementById('odo2_start').value),
          end   : fromLocalInput(sh.getElementById('odo2_end').value)
        };
        fetch(window.ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload) })
        .then(r=>r.json()).then(r=>{
          if(!r.success) throw new Error(r?.data?.msg || 'Error al actualizar');
          modal.classList.remove('open'); showToast('Cita actualizada');
          this.reloadRange();
        }).catch(e=> alert(e.message));
      });

      // Aprobar
      sh.getElementById('odo2-approve').addEventListener('click', ()=>{
        const id = sh.getElementById('odo2_post').value; if(!id) return;
        const payload = { action:'asetec_odo_approve', nonce:ASETEC_ODO_ADMIN2?.nonce, id };
        fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload) })
        .then(r=>r.json()).then(r=>{
          if(!r.success) throw new Error(r?.data?.msg || 'Error al aprobar');
          modal.classList.remove('open'); showToast('Cita aprobada'); this.reloadRange();
        }).catch(e=> alert(e.message));
      });

      // Cancelar
      sh.getElementById('odo2-cancel').addEventListener('click', ()=>{
        const id = sh.getElementById('odo2_post').value; if(!id) return;
        if(!confirm('¿Seguro de cancelar esta cita?')) return;
        const payload = { action:'asetec_odo_cancel', nonce:ASETEC_ODO_ADMIN2?.nonce, id };
        fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload) })
        .then(r=>r.json()).then(r=>{
          if(!r.success) throw new Error(r?.data?.msg || 'Error al cancelar');
          modal.classList.remove('open'); showToast('Cita cancelada'); this.reloadRange();
        }).catch(e=> alert(e.message));
      });

      // Realizada
      sh.getElementById('odo2-done').addEventListener('click', ()=>{
        const id = sh.getElementById('odo2_post').value; if(!id) return;
        const payload = { action:'asetec_odo_mark_done', nonce:ASETEC_ODO_ADMIN2?.nonce, id };
        fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(payload) })
        .then(r=>r.json()).then(r=>{
          if(!r.success) throw new Error(r?.data?.msg || 'Error al marcar realizada');
          modal.classList.remove('open'); showToast('Marcada como realizada'); this.reloadRange();
        }).catch(e=> alert(e.message));
      });

      // Helpers modal
      this.openCreateModal = (sh)=>{
        const now = new Date();
        now.setSeconds(0,0);
        const dur = 40;
        sh.getElementById('odo2_post').value = '';
        sh.getElementById('odo2_start').value = toLocalInput(now.toISOString().slice(0,19).replace('T',' '));
        const end = new Date(now); end.setMinutes(end.getMinutes()+dur);
        sh.getElementById('odo2_end').value = toLocalInput(end.toISOString().slice(0,19).replace('T',' '));
        sh.getElementById('odo2_dur').value = '40';
        sh.getElementById('odo2_status').value = 'pendiente';
        sh.getElementById('odo2_name').value = '';
        sh.getElementById('odo2_cid').value = '';
        sh.getElementById('odo2_email').value = '';
        sh.getElementById('odo2_phone').value = '';
        sh.getElementById('odo2-save').style.display = '';
        sh.getElementById('odo2-update').style.display = 'none';
        modal.classList.add('open');
      };

      this.openEditModal = (sh, data)=>{
        sh.getElementById('odo2_post').value = data.id || '';
        sh.getElementById('odo2_start').value = toLocalInput((data.start||'').replace('T',' '));
        sh.getElementById('odo2_end').value   = toLocalInput((data.end||'').replace('T',' '));
        sh.getElementById('odo2_status').value = data.estado || '';
        sh.getElementById('odo2_name').value   = data.nombre || '';
        sh.getElementById('odo2_cid').value    = data.cedula || '';
        sh.getElementById('odo2_email').value  = data.correo || '';
        sh.getElementById('odo2_phone').value  = data.telefono || '';
        sh.getElementById('odo2-save').style.display = 'none';
        sh.getElementById('odo2-update').style.display = '';
        modal.classList.add('open');
      };
    }

    initCalendar(calEl, sh, ui){
      // eslint-disable-next-line no-undef
      const Calendar = window.toastui.Calendar;

      const calendar = new Calendar(calEl, {
        defaultView: 'week',
        isReadOnly: false,
        usageStatistics: false,
        week: {
          startDayOfWeek: 1,
          workweek: true,
          hourStart: 7,
          hourEnd: 20,
          timeGrid: { unit: 'minute', cellHeight: 34 }
        },
        gridSelection: { enableDblClick: true, enableClick: true },
        useFormPopup: false,
        useDetailPopup: false,
        template: {
          time(event){
            const est = (event.raw && event.raw.estado) || '';
            const chip = `<span style="background:${estadoColor(est)};color:#fff;border-radius:999px;padding:0 6px;font-size:10px;margin-right:6px">${est.replace('_',' ')}</span>`;
            return `${chip}${event.title || '(Sin nombre)'}`;
          }
        }
      });

      this.state.calendar = calendar;

      // Carga de eventos del servidor
      const fetchEvents = async (start, end)=>{
        const payload = new URLSearchParams({
          action: 'asetec_odo_events',
          nonce : window.ASETEC_ODO_ADMIN2?.nonce,
          start : start.toISOString(),
          end   : end.toISOString()
        });
        const res = await fetch(window.ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload });
        const json = await res.json();
        if(!json?.success) throw new Error(json?.data?.msg || 'Error eventos');
        const events = (json.data?.events || []).map(ev=>{
          const est  = ev.extendedProps && ev.extendedProps.estado;
          return {
            id: String(ev.id || ev.extendedProps?.post_id || ''),
            calendarId: 'odo',
            title: ev.title || '',
            start: ev.start, end: ev.end,
            raw: Object.assign({}, ev.extendedProps || {}, { estado: est }),
            backgroundColor: estadoColor(est),
            borderColor: estadoColor(est)
          };
        });
        return events;
      };

      const estadoColor = (s)=> ({pendiente:'#f59e0b', aprobada:'#3b82f6', realizada:'#10b981', cancelada_usuario:'#ef4444', cancelada_admin:'#ef4444', reprogramada:'#8b5cf6'}[s] || '#64748b');

      const reload = async ()=>{
        const range = calendar.getDateRange();
        const events = await fetchEvents(range.start, range.end);
        calendar.clear();
        // Filtro por estado y búsqueda
        const fil = events.filter(ev=>{
          const estOK = this.state.statusVisible.has((ev.raw?.estado)||'');
          const q = this.state.search;
          const title = (ev.title||'').toLowerCase();
          const ced   = (ev.raw?.cedula||'').toLowerCase();
          const qOK = !q || title.includes(q) || ced.includes(q);
          return estOK && qOK;
        });
        calendar.createEvents(fil);
      };

      // Navegación / cambio de vista → recarga
      calendar.on('afterRender', ()=>{}); // hook
      calendar.on('beforeRender', ()=>{}); // hook
      calendar.on('afterViewRender', ()=> reload().catch(console.error));

      // Crear por selección
      calendar.on('selectDateTime', (ev)=>{
        const now = new Date();
        if (ev.start < now) { ui.showToast('No puede crear en el pasado'); return; }
        // Prefill modal
        const shd = sh;
        shd.getElementById('odo2_post').value = '';
        shd.getElementById('odo2_start').value = toLocal(ev.start);
        shd.getElementById('odo2_end').value   = toLocal(ev.end);
        shd.getElementById('odo2_status').value = 'pendiente';
        shd.getElementById('odo2_name').value = '';
        shd.getElementById('odo2_cid').value = '';
        shd.getElementById('odo2_email').value = '';
        shd.getElementById('odo2_phone').value = '';
        shd.getElementById('odo2-save').style.display = '';
        shd.getElementById('odo2-update').style.display = 'none';
        sh.querySelector('.modal').classList.add('open');

        function toLocal(d){ return (new Date(d)).toISOString().slice(0,19).replace('T',' ' ).replace(' ','T'); }
      });

      // Click en evento → abrir modal con datos
      calendar.on('clickEvent', async (ev)=>{
        const id = ev?.event?.id; if(!id) return;
        try{
          const payload = new URLSearchParams({ action:'asetec_odo_show', nonce:ASETEC_ODO_ADMIN2?.nonce, id });
          const res = await fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload });
          const json = await res.json();
          if(!json?.success) throw new Error(json?.data?.msg || 'Error al cargar cita');

          const d = json.data || {};
          sh.getElementById('odo2_post').value   = id;
          sh.getElementById('odo2_start').value  = (d.start||'').replace(' ','T').slice(0,16);
          sh.getElementById('odo2_end').value    = (d.end||'').replace(' ','T').slice(0,16);
          sh.getElementById('odo2_status').value = d.estado || '';
          sh.getElementById('odo2_name').value   = d.nombre || '';
          sh.getElementById('odo2_cid').value    = d.cedula || '';
          sh.getElementById('odo2_email').value  = d.correo || '';
          sh.getElementById('odo2_phone').value  = d.telefono || '';
          sh.getElementById('odo2-save').style.display   = 'none';
          sh.getElementById('odo2-update').style.display = '';
          sh.querySelector('.modal').classList.add('open');
        } catch(e){
          alert(e.message);
        }
      });

      // Drag / Resize → reprogramar
      calendar.on('drop', async (ev)=>{
        const id = ev?.event?.id;
        if(!id) return;
        try{
          const payload = new URLSearchParams({
            action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN2?.nonce, id,
            start: toPhp(ev.event.start), end: toPhp(ev.event.end)
          });
          const r = await fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload });
          const j = await r.json();
          if(!j?.success) throw new Error(j?.data?.msg || 'No se pudo reprogramar');
          ui.showToast('Cita reprogramada');
          await reload();
        } catch(e){ alert(e.message); }
      });
      calendar.on('resize', async (ev)=>{
        const id = ev?.event?.id;
        if(!id) return;
        try{
          const payload = new URLSearchParams({
            action:'asetec_odo_reschedule', nonce:ASETEC_ODO_ADMIN2?.nonce, id,
            start: toPhp(ev.event.start), end: toPhp(ev.event.end)
          });
          const r = await fetch(ASETEC_ODO_ADMIN2?.ajax, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload });
          const j = await r.json();
          if(!j?.success) throw new Error(j?.data?.msg || 'No se pudo ajustar');
          ui.showToast('Duración actualizada');
          await reload();
        } catch(e){ alert(e.message); }
      });

      const toPhp = (d)=> {
        if (!d) return '';
        const x = new Date(d);
        const pad = n=> String(n).padStart(2,'0');
        return `${x.getFullYear()}-${pad(x.getMonth()+1)}-${pad(x.getDate())} ${pad(x.getHours())}:${pad(x.getMinutes())}:00`;
      };

      this.reloadRange = ()=> reload().catch(console.error);

      // primera carga
      reload().catch(err=>{
        console.error(err);
        calEl.innerHTML = '<p style="padding:12px;color:#b91c1c">Error cargando eventos.</p>';
      });
    }
  }

  customElements.define('asetec-odo-agenda', AsetecAgenda);
})();
