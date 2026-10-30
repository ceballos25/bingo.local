import { api } from '../../api.js';
import { requireMesa, esAdministrador, getUsuario } from '../../auth.js';
import {
  emptyHtml,
  escapeHtml,
  loadingHtml,
  setButtonBusy,
  toast,
} from '../../ui.js';

const PAGE_SIZE = 10;

function rolBadge(rol) {
  const map = {
    jugador: 'badge text-bg-primary',
    operador: 'badge text-bg-warning',
    administrador: 'badge text-bg-dark',
  };
  const cls = map[rol] || 'badge text-bg-light border';
  return `<span class="${cls}">${escapeHtml(String(rol || ''))}</span>`;
}

function estadoBadge(estado) {
  if (estado === 'activo') {
    return '<span class="badge text-bg-success">Activo</span>';
  }
  return `<span class="badge text-bg-secondary">${escapeHtml(String(estado || 'inactivo'))}</span>`;
}

function etiquetaCiudad(c) {
  return c?.etiqueta || (c?.departamento ? `${c.nombre} (${c.departamento})` : c?.nombre || c?.ciudad || String(c?.id || ''));
}

function ciudadNombre(ciudades, ciudadId) {
  const c = (ciudades || []).find((x) => Number(x.id) === Number(ciudadId));
  return c ? etiquetaCiudad(c) : '—';
}

function opcionesCiudad(ciudades, selectedId) {
  return (ciudades || [])
    .map((c) => {
      const sel = Number(c.id) === Number(selectedId) ? ' selected' : '';
      return `<option value="${c.id}"${sel}>${escapeHtml(etiquetaCiudad(c))}</option>`;
    })
    .join('');
}

function puedeEditarPersona(u) {
  if (esAdministrador()) return true;
  return String(u?.rol || '') === 'jugador';
}

function contarRoles(lista) {
  const c = { jugador: 0, operador: 0, administrador: 0, activo: 0, inactivo: 0 };
  for (const u of lista) {
    const r = String(u.rol || '');
    if (c[r] !== undefined) c[r] += 1;
    if (u.estado === 'activo') c.activo += 1;
    else c.inactivo += 1;
  }
  return c;
}

function statsHtml(lista) {
  const c = contarRoles(lista);
  const total = Math.max(lista.length, 1);
  const bar = (n, cls) =>
    `<div class="mesa-stat-bar__seg ${cls}" style="width:${((n / total) * 100).toFixed(1)}%" title="${n}"></div>`;

  return `
    <div class="mesa-stats border bg-white p-3 mb-3">
      <div class="row g-2 mb-2">
        <div class="col-6 col-md-3"><div class="small text-secondary">Total</div><div class="fs-5 fw-bold">${lista.length}</div></div>
        <div class="col-6 col-md-3"><div class="small text-secondary">Jugadores</div><div class="fs-5 fw-bold">${c.jugador}</div></div>
        <div class="col-6 col-md-3"><div class="small text-secondary">Operadores</div><div class="fs-5 fw-bold">${c.operador}</div></div>
        <div class="col-6 col-md-3"><div class="small text-secondary">Admins</div><div class="fs-5 fw-bold">${c.administrador}</div></div>
      </div>
      <div class="mesa-stat-bar" aria-hidden="true">
        ${bar(c.jugador, 'mesa-stat-bar__seg--jugador')}
        ${bar(c.operador, 'mesa-stat-bar__seg--operador')}
        ${bar(c.administrador, 'mesa-stat-bar__seg--admin')}
      </div>
      <div class="small text-secondary mt-1">${c.activo} activos · ${c.inactivo} inactivos</div>
    </div>`;
}

function formClienteHtml(ciudades, u = null) {
  const edit = u != null;
  const admin = esAdministrador();
  const roles = admin ? ['jugador', 'operador', 'administrador'] : ['jugador'];
  const rolBloqueado = !admin;

  return `
    <form class="mesa-cliente-form border bg-white p-3 mb-3" id="formCliente" data-edit-id="${edit ? u.id : ''}">
      <h2 class="h6 font-brand mb-3">${edit ? 'Editar persona' : 'Nueva persona'}</h2>
      <div class="row g-2">
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliNombre">Nombre</label>
          <input class="form-control" id="cliNombre" required maxlength="80" value="${escapeHtml(edit ? u.nombre || '' : '')}">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliApellido">Apellido</label>
          <input class="form-control" id="cliApellido" required maxlength="80" value="${escapeHtml(edit ? u.apellido || '' : '')}">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliCelular">Celular</label>
          <input class="form-control" id="cliCelular" required maxlength="20" inputmode="tel" value="${escapeHtml(edit ? u.celular || '' : '')}">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliCorreo">Correo</label>
          <input class="form-control" id="cliCorreo" type="email" required maxlength="120" value="${escapeHtml(edit ? u.correo || '' : '')}">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliCiudad">Ciudad</label>
          <select class="form-select" id="cliCiudad" required>
            <option value="">Elige ciudad</option>
            ${opcionesCiudad(ciudades, edit ? u.ciudadId : null)}
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1" for="cliRol">Rol</label>
          <select class="form-select" id="cliRol" ${rolBloqueado ? 'disabled' : ''}>
            ${roles
              .map(
                (r) =>
                  `<option value="${r}" ${edit && u.rol === r ? 'selected' : !edit && r === 'jugador' ? 'selected' : ''}>${r}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small mb-1" for="cliEstado">Estado</label>
          <select class="form-select" id="cliEstado" ${edit ? '' : 'disabled'}>
            <option value="activo" ${!edit || u.estado === 'activo' ? 'selected' : ''}>activo</option>
            <option value="inactivo" ${edit && u.estado === 'inactivo' ? 'selected' : ''}>inactivo</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cliPassword">${edit ? 'Nueva contraseña (opcional)' : 'Contraseña (opcional)'}</label>
          <input class="form-control" id="cliPassword" type="password" minlength="6" autocomplete="new-password" placeholder="${edit ? 'Dejar vacío para no cambiar' : 'Mín. 6 caracteres'}">
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary" id="btnGuardarCliente">
          ${edit ? 'Guardar cambios' : 'Crear'}
        </button>
        ${edit ? '<button type="button" class="btn btn-outline-secondary" id="btnCancelarEdit">Cancelar</button>' : ''}
      </div>
    </form>`;
}

function filtrarLista(lista, { q, rol, estado }) {
  const needle = String(q || '').trim().toLowerCase();
  return lista.filter((u) => {
    if (rol !== 'todos' && String(u.rol) !== rol) return false;
    if (estado !== 'todos' && String(u.estado) !== estado) return false;
    if (!needle) return true;
    const blob = `${u.nombre || ''} ${u.apellido || ''} ${u.celular || ''} ${u.correo || ''}`.toLowerCase();
    return blob.includes(needle);
  });
}

function tablaHtml(rows, ciudades) {
  if (rows.length === 0) {
    return emptyHtml('No hay resultados con estos filtros.');
  }

  const trs = rows
    .map((u) => {
      const nombre = escapeHtml(`${u.nombre || ''} ${u.apellido || ''}`.trim() || 'Sin nombre');
      const editable = puedeEditarPersona(u);
      return `
        <tr class="${u.estado === 'activo' ? '' : 'table-secondary'}">
          <td class="text-secondary small">${escapeHtml(String(u.id))}</td>
          <td>
            <div class="fw-semibold">${nombre}</div>
            <div class="small mesa-cliente-contacto d-md-none">
              <a class="mesa-cliente-contacto__link" href="tel:${escapeHtml(u.celular || '')}">${escapeHtml(u.celular || '—')}</a>
            </div>
          </td>
          <td class="d-none d-md-table-cell">
            <a class="mesa-cliente-contacto__link" href="tel:${escapeHtml(u.celular || '')}">${escapeHtml(u.celular || '—')}</a>
          </td>
          <td class="d-none d-lg-table-cell">
            <a class="mesa-cliente-contacto__link text-break" href="mailto:${escapeHtml(u.correo || '')}">${escapeHtml(u.correo || '—')}</a>
          </td>
          <td class="d-none d-xl-table-cell small">${escapeHtml(ciudadNombre(ciudades, u.ciudadId))}</td>
          <td>${rolBadge(u.rol)}</td>
          <td>${estadoBadge(u.estado)}</td>
          <td class="text-end">
            ${
              editable
                ? `<button type="button" class="btn btn-sm btn-outline-primary" data-editar="${u.id}">Editar</button>`
                : '<span class="small text-secondary">—</span>'
            }
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
            <th class="d-none d-md-table-cell" scope="col">Celular</th>
            <th class="d-none d-lg-table-cell" scope="col">Correo</th>
            <th class="d-none d-xl-table-cell" scope="col">Ciudad</th>
            <th scope="col">Rol</th>
            <th scope="col">Estado</th>
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

export async function renderMesaClientes(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  let filtroRol = 'todos';
  let filtroEstado = 'todos';
  let busqueda = '';
  let page = 1;
  let editId = null;
  let showForm = false;
  let ciudades = [];
  let lista = [];

  const paint = async (reload = true) => {
    if (reload) {
      root.innerHTML = loadingHtml('Cargando personas…');
      try {
        const [users, cities] = await Promise.all([
          api('/usuarios/listar'),
          api('/ciudades/listar'),
        ]);
        lista = Array.isArray(users) ? users : [];
        ciudades = Array.isArray(cities) ? cities : [];
      } catch (e) {
        toast(e.message || 'Error al cargar');
        root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
        return;
      }
    }

    const editUser = editId
      ? lista.find((u) => Number(u.id) === Number(editId)) || null
      : null;
    const filtrados = filtrarLista(lista, {
      q: busqueda,
      rol: filtroRol,
      estado: filtroEstado,
    });
    const totalPages = Math.max(1, Math.ceil(filtrados.length / PAGE_SIZE));
    if (page > totalPages) page = totalPages;
    const slice = filtrados.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
          <h1 class="h4 font-brand mb-1">Clientes y equipo</h1>
          <p class="text-secondary mb-0">Tabla con búsqueda, filtros y paginación.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <button type="button" class="btn btn-primary btn-sm" id="btnNuevaPersona">Nueva persona</button>
          <a class="btn btn-outline-secondary btn-sm" href="#/mesa">Volver</a>
        </div>
      </div>

      ${statsHtml(lista)}

      ${showForm || editUser ? formClienteHtml(ciudades, editUser) : ''}

      <div class="border bg-white p-3 mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-md-5">
            <label class="form-label small mb-1" for="cliBuscar">Buscar</label>
            <input class="form-control" id="cliBuscar" type="search" placeholder="Nombre, celular o correo" value="${escapeHtml(busqueda)}">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="cliFiltroRol">Rol</label>
            <select class="form-select" id="cliFiltroRol">
              <option value="todos" ${filtroRol === 'todos' ? 'selected' : ''}>Todos</option>
              <option value="jugador" ${filtroRol === 'jugador' ? 'selected' : ''}>Jugadores</option>
              <option value="operador" ${filtroRol === 'operador' ? 'selected' : ''}>Operadores</option>
              <option value="administrador" ${filtroRol === 'administrador' ? 'selected' : ''}>Admins</option>
            </select>
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="cliFiltroEstado">Estado</label>
            <select class="form-select" id="cliFiltroEstado">
              <option value="todos" ${filtroEstado === 'todos' ? 'selected' : ''}>Todos</option>
              <option value="activo" ${filtroEstado === 'activo' ? 'selected' : ''}>Activo</option>
              <option value="inactivo" ${filtroEstado === 'inactivo' ? 'selected' : ''}>Inactivo</option>
            </select>
          </div>
          <div class="col-md-2">
            <button type="button" class="btn btn-outline-secondary w-100" id="cliLimpiarFiltros">Limpiar</button>
          </div>
        </div>
      </div>

      ${tablaHtml(slice, ciudades)}
      ${pagerHtml(page, totalPages, filtrados.length)}`;

    const applyFiltersFromDom = () => {
      busqueda = root.querySelector('#cliBuscar')?.value || '';
      filtroRol = root.querySelector('#cliFiltroRol')?.value || 'todos';
      filtroEstado = root.querySelector('#cliFiltroEstado')?.value || 'todos';
      page = 1;
      paint(false);
    };

    root.querySelector('#cliBuscar')?.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        applyFiltersFromDom();
      }
    });
    root.querySelector('#cliBuscar')?.addEventListener('change', applyFiltersFromDom);
    root.querySelector('#cliFiltroRol')?.addEventListener('change', applyFiltersFromDom);
    root.querySelector('#cliFiltroEstado')?.addEventListener('change', applyFiltersFromDom);
    root.querySelector('#cliLimpiarFiltros')?.addEventListener('click', () => {
      busqueda = '';
      filtroRol = 'todos';
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

    root.querySelector('#btnNuevaPersona')?.addEventListener('click', () => {
      editId = null;
      showForm = true;
      paint(false);
      root.querySelector('#formCliente')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    root.querySelectorAll('[data-editar]').forEach((btn) => {
      btn.addEventListener('click', () => {
        editId = Number(btn.getAttribute('data-editar'));
        showForm = true;
        paint(false);
        root.querySelector('#formCliente')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });

    root.querySelector('#btnCancelarEdit')?.addEventListener('click', () => {
      editId = null;
      showForm = false;
      paint(false);
    });

    const form = root.querySelector('#formCliente');
    form?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = root.querySelector('#btnGuardarCliente');
      const yo = getUsuario();
      const ciudadId = Number(root.querySelector('#cliCiudad')?.value);
      if (!ciudadId) {
        toast('Elige una ciudad');
        return;
      }

      const payload = {
        nombre: root.querySelector('#cliNombre')?.value?.trim() || '',
        apellido: root.querySelector('#cliApellido')?.value?.trim() || '',
        celular: root.querySelector('#cliCelular')?.value?.trim() || '',
        correo: root.querySelector('#cliCorreo')?.value?.trim() || '',
        ciudadId,
      };
      if (esAdministrador()) {
        payload.rol = root.querySelector('#cliRol')?.value || 'jugador';
      } else {
        payload.rol = 'jugador';
      }
      const pass = root.querySelector('#cliPassword')?.value || '';
      if (pass) payload.password = pass;

      const idEdit = Number(form.getAttribute('data-edit-id') || 0);
      if (idEdit > 0) {
        payload.estado = root.querySelector('#cliEstado')?.value || 'activo';
        if (!esAdministrador()) {
          delete payload.rol;
        }
        if (yo?.id && Number(yo.id) === idEdit && payload.estado === 'inactivo') {
          toast('No puedes desactivarte a ti mismo');
          return;
        }
      }

      setButtonBusy(btn, true, 'Guardando…');
      try {
        if (idEdit > 0) {
          await api('/usuarios/actualizar?id=' + idEdit, {
            method: 'PUT',
            body: payload,
          });
          toast('Persona actualizada', 'success');
        } else {
          await api('/usuarios/crear', {
            method: 'POST',
            body: payload,
          });
          toast('Persona creada', 'success');
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
