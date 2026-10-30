import { api } from '../../api.js';
import { requireMesa, esAdministrador, getUsuario, setUsuario } from '../../auth.js';
import {
  emptyHtml,
  escapeHtml,
  formatFecha,
  loadingHtml,
  setButtonBusy,
  toast,
} from '../../ui.js';

const PAGE_SIZE = 10;

function estadoBadge(estado) {
  if (estado === 'activo') {
    return '<span class="badge text-bg-success">Activo</span>';
  }
  return `<span class="badge text-bg-secondary">${escapeHtml(String(estado || 'inactivo'))}</span>`;
}

function formOrgHtml(org = null) {
  const edit = org != null;
  return `
    <form class="mesa-org-form border bg-white p-3 mb-3" id="formOrg" data-edit-id="${edit ? org.id : ''}">
      <h2 class="h6 font-brand mb-3">${edit ? 'Editar organización' : 'Nueva organización'}</h2>
      <div class="row g-2">
        <div class="col-md-8">
          <label class="form-label small mb-1" for="orgNombre">Nombre</label>
          <input class="form-control" id="orgNombre" required maxlength="120"
            value="${escapeHtml(edit ? org.nombre || '' : '')}" placeholder="Ej. Bingo del barrio">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1" for="orgEstado">Estado</label>
          <select class="form-select" id="orgEstado" ${edit ? '' : 'disabled'}>
            <option value="activo" ${!edit || org.estado === 'activo' ? 'selected' : ''}>activo</option>
            <option value="inactivo" ${edit && org.estado === 'inactivo' ? 'selected' : ''}>inactivo</option>
          </select>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary" id="btnGuardarOrg">${edit ? 'Guardar' : 'Crear'}</button>
        <button type="button" class="btn btn-outline-secondary" id="btnCancelarOrg">Cancelar</button>
      </div>
    </form>`;
}

function statsOrgHtml(lista, miOrgId) {
  const activas = lista.filter((o) => o.estado === 'activo').length;
  const total = Math.max(lista.length, 1);
  return `
    <div class="mesa-stats border bg-white p-3 mb-3">
      <div class="row g-2 mb-2">
        <div class="col-4"><div class="small text-secondary">Total</div><div class="fs-5 fw-bold">${lista.length}</div></div>
        <div class="col-4"><div class="small text-secondary">Activas</div><div class="fs-5 fw-bold">${activas}</div></div>
        <div class="col-4"><div class="small text-secondary">Inactivas</div><div class="fs-5 fw-bold">${lista.length - activas}</div></div>
      </div>
      <div class="mesa-stat-bar" aria-hidden="true">
        <div class="mesa-stat-bar__seg mesa-stat-bar__seg--jugador" style="width:${((activas / total) * 100).toFixed(1)}%"></div>
        <div class="mesa-stat-bar__seg mesa-stat-bar__seg--admin" style="width:${(((lista.length - activas) / total) * 100).toFixed(1)}%"></div>
      </div>
      <div class="small text-secondary mt-1">Tu sesión: org #${escapeHtml(String(miOrgId || '—'))}</div>
    </div>`;
}

function tablaOrgHtml(rows, miOrgId) {
  if (rows.length === 0) {
    return emptyHtml('No hay organizaciones con estos filtros.');
  }

  const trs = rows
    .map((o) => {
      const esMia = Number(o.id) === Number(miOrgId);
      return `
        <tr class="${o.estado === 'activo' ? '' : 'table-secondary'}">
          <td class="small text-secondary">${escapeHtml(String(o.id))}</td>
          <td>
            <div class="fw-semibold">${escapeHtml(o.nombre || '—')}${esMia ? ' <span class="badge text-bg-info">Tu org</span>' : ''}</div>
          </td>
          <td>${estadoBadge(o.estado)}</td>
          <td class="d-none d-md-table-cell small text-secondary">${escapeHtml(formatFecha(o.creadoEn))}</td>
          <td class="d-none d-lg-table-cell small text-secondary">${escapeHtml(formatFecha(o.modificadoEn))}</td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-primary" data-editar-org="${o.id}">Editar</button>
          </td>
        </tr>`;
    })
    .join('');

  return `
    <div class="table-responsive border bg-white mesa-data-table">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">ID</th>
            <th scope="col">Nombre</th>
            <th scope="col">Estado</th>
            <th class="d-none d-md-table-cell" scope="col">Creada</th>
            <th class="d-none d-lg-table-cell" scope="col">Modificada</th>
            <th scope="col" class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>${trs}</tbody>
      </table>
    </div>`;
}

function pagerHtml(page, totalPages, total) {
  if (totalPages <= 1) {
    return `<p class="small text-secondary mt-2 mb-0">${total} registro${total === 1 ? '' : 's'}</p>`;
  }
  return `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
      <p class="small text-secondary mb-0">${total} registros · pág. ${page} / ${totalPages}</p>
      <div class="btn-group">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-page="prev" ${page <= 1 ? 'disabled' : ''}>Anterior</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-page="next" ${page >= totalPages ? 'disabled' : ''}>Siguiente</button>
      </div>
    </div>`;
}

/** Operador: solo lectura de su org. */
async function renderOperadorOrg(root, yo, orgId) {
  root.innerHTML = loadingHtml('Cargando organización…');
  try {
    const org = await api('/organizaciones/buscar?id=' + encodeURIComponent(orgId));
    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h1 class="h4 font-brand mb-1">Organización</h1>
          <p class="text-secondary mb-0">Solo el administrador puede editar o crear tenants.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="#/mesa">Volver</a>
      </div>
      <div class="border bg-white p-3">
        <dl class="row mb-0">
          <dt class="col-sm-3">Nombre</dt><dd class="col-sm-9 fw-semibold">${escapeHtml(org.nombre || '—')}</dd>
          <dt class="col-sm-3">Estado</dt><dd class="col-sm-9">${estadoBadge(org.estado)}</dd>
          <dt class="col-sm-3">ID</dt><dd class="col-sm-9">${escapeHtml(String(org.id))}</dd>
          <dt class="col-sm-3">Creada</dt><dd class="col-sm-9">${escapeHtml(formatFecha(org.creadoEn))}</dd>
        </dl>
      </div>`;
  } catch (e) {
    toast(e.message || 'Error al cargar');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
  }
}

export async function renderMesaOrganizacion(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  const yo = getUsuario() || {};
  const miOrgId = Number(yo.organizacionId);
  if (!miOrgId) {
    root.innerHTML = emptyHtml('No hay organización en la sesión.');
    return;
  }

  if (!esAdministrador()) {
    await renderOperadorOrg(root, yo, miOrgId);
    return;
  }

  let lista = [];
  let busqueda = '';
  let filtroEstado = 'todos';
  let page = 1;
  let editId = null;
  let showForm = false;

  const paint = async (reload = true) => {
    if (reload) {
      root.innerHTML = loadingHtml('Cargando organizaciones…');
      try {
        const data = await api('/organizaciones/listar');
        lista = Array.isArray(data) ? data : [];
      } catch (e) {
        toast(e.message || 'Error al cargar');
        root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
        return;
      }
    }

    const needle = busqueda.trim().toLowerCase();
    const filtrados = lista.filter((o) => {
      if (filtroEstado !== 'todos' && String(o.estado) !== filtroEstado) return false;
      if (!needle) return true;
      return String(o.nombre || '').toLowerCase().includes(needle) || String(o.id).includes(needle);
    });
    const totalPages = Math.max(1, Math.ceil(filtrados.length / PAGE_SIZE));
    if (page > totalPages) page = totalPages;
    const slice = filtrados.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
    const editOrg = editId
      ? lista.find((o) => Number(o.id) === Number(editId)) || null
      : null;

    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
          <h1 class="h4 font-brand mb-1">Organizaciones</h1>
          <p class="text-secondary mb-0">CRUD de tenants · solo administrador.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary btn-sm" id="btnNuevaOrg">Nueva organización</button>
          <a class="btn btn-outline-secondary btn-sm" href="#/mesa">Volver</a>
        </div>
      </div>

      ${statsOrgHtml(lista, miOrgId)}
      ${showForm || editOrg ? formOrgHtml(editOrg) : ''}

      <div class="border bg-white p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label small mb-1" for="orgBuscar">Buscar</label>
            <input class="form-control" id="orgBuscar" type="search" placeholder="Nombre o ID" value="${escapeHtml(busqueda)}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="orgFiltroEstado">Estado</label>
            <select class="form-select" id="orgFiltroEstado">
              <option value="todos" ${filtroEstado === 'todos' ? 'selected' : ''}>Todos</option>
              <option value="activo" ${filtroEstado === 'activo' ? 'selected' : ''}>Activo</option>
              <option value="inactivo" ${filtroEstado === 'inactivo' ? 'selected' : ''}>Inactivo</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <button type="button" class="btn btn-outline-secondary w-100" id="orgLimpiar">Limpiar</button>
          </div>
        </div>
      </div>

      ${tablaOrgHtml(slice, miOrgId)}
      ${pagerHtml(page, totalPages, filtrados.length)}`;

    const applyFilters = () => {
      busqueda = root.querySelector('#orgBuscar')?.value || '';
      filtroEstado = root.querySelector('#orgFiltroEstado')?.value || 'todos';
      page = 1;
      paint(false);
    };

    root.querySelector('#orgBuscar')?.addEventListener('change', applyFilters);
    root.querySelector('#orgBuscar')?.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        applyFilters();
      }
    });
    root.querySelector('#orgFiltroEstado')?.addEventListener('change', applyFilters);
    root.querySelector('#orgLimpiar')?.addEventListener('click', () => {
      busqueda = '';
      filtroEstado = 'todos';
      page = 1;
      paint(false);
    });

    root.querySelectorAll('[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const dir = btn.getAttribute('data-page');
        if (dir === 'prev') page = Math.max(1, page - 1);
        if (dir === 'next') page += 1;
        paint(false);
      });
    });

    root.querySelector('#btnNuevaOrg')?.addEventListener('click', () => {
      editId = null;
      showForm = true;
      paint(false);
      root.querySelector('#formOrg')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    root.querySelectorAll('[data-editar-org]').forEach((btn) => {
      btn.addEventListener('click', () => {
        editId = Number(btn.getAttribute('data-editar-org'));
        showForm = true;
        paint(false);
        root.querySelector('#formOrg')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    root.querySelector('#btnCancelarOrg')?.addEventListener('click', () => {
      editId = null;
      showForm = false;
      paint(false);
    });

    root.querySelector('#formOrg')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const form = root.querySelector('#formOrg');
      const btn = root.querySelector('#btnGuardarOrg');
      const nombre = root.querySelector('#orgNombre')?.value?.trim() || '';
      const estado = root.querySelector('#orgEstado')?.value || 'activo';
      if (!nombre) {
        toast('El nombre es obligatorio');
        return;
      }
      const idEdit = Number(form?.getAttribute('data-edit-id') || 0);
      setButtonBusy(btn, true, 'Guardando…');
      try {
        if (idEdit > 0) {
          const actualizada = await api(
            '/organizaciones/actualizar?id=' + encodeURIComponent(idEdit),
            { method: 'PUT', body: { nombre, estado } }
          );
          if (yo && Number(idEdit) === Number(miOrgId) && actualizada?.nombre) {
            setUsuario({ ...yo, organizacionNombre: actualizada.nombre });
          }
          toast('Organización actualizada', 'success');
        } else {
          await api('/organizaciones/crear', {
            method: 'POST',
            body: { nombre },
          });
          toast('Organización creada', 'success');
        }
        editId = null;
        showForm = false;
        await paint(true);
      } catch (e) {
        toast(e.message || 'No se pudo guardar');
        setButtonBusy(btn, false);
      }
    });
  };

  await paint(true);
}
