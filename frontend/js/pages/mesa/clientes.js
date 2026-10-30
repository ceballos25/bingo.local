import { api } from '../../api.js';
import { requireMesa, esAdministrador, getUsuario } from '../../auth.js';
import {
  emptyHtml,
  escapeHtml,
  loadingHtml,
  setButtonBusy,
  toast,
} from '../../ui.js';

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

function ciudadNombre(ciudades, ciudadId) {
  const c = (ciudades || []).find((x) => Number(x.id) === Number(ciudadId));
  return c ? c.nombre || c.ciudad || String(c.id) : '—';
}

function opcionesCiudad(ciudades, selectedId) {
  return (ciudades || [])
    .map((c) => {
      const sel = Number(c.id) === Number(selectedId) ? ' selected' : '';
      return `<option value="${c.id}"${sel}>${escapeHtml(c.nombre || c.ciudad || String(c.id))}</option>`;
    })
    .join('');
}

function puedeEditarPersona(u) {
  if (esAdministrador()) return true;
  return String(u?.rol || '') === 'jugador';
}

function cardClienteHtml(u, ciudades) {
  const nombre = escapeHtml(`${u.nombre || ''} ${u.apellido || ''}`.trim() || 'Sin nombre');
  const editable = puedeEditarPersona(u);
  return `
    <article class="mesa-cliente-card border bg-white ${u.estado === 'activo' ? '' : 'mesa-cliente-card--off'}" data-id="${u.id}">
      <div class="d-flex justify-content-between gap-2 align-items-start">
        <div class="min-w-0">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
            <h2 class="h6 font-brand mb-0">${nombre}</h2>
            ${rolBadge(u.rol)}
            ${estadoBadge(u.estado)}
          </div>
          <div class="small">
            <a class="text-decoration-none" href="tel:${escapeHtml(u.celular || '')}">${escapeHtml(u.celular || '—')}</a>
            ·
            <a class="text-decoration-none text-break" href="mailto:${escapeHtml(u.correo || '')}">${escapeHtml(u.correo || '—')}</a>
          </div>
          <div class="small text-secondary">Ciudad: ${escapeHtml(ciudadNombre(ciudades, u.ciudadId))}</div>
        </div>
        ${
          editable
            ? `<button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-editar="${u.id}">Editar</button>`
            : '<span class="small text-secondary flex-shrink-0">Solo admin</span>'
        }
      </div>
    </article>`;
}

function formClienteHtml(ciudades, u = null) {
  const edit = u != null;
  const admin = esAdministrador();
  // Operador solo crea/edita jugadores; admin asigna cualquier rol.
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

export async function renderMesaClientes(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  let filtro = 'jugador';
  let editId = null;
  let ciudades = [];
  let lista = [];

  const paint = async () => {
    root.innerHTML = loadingHtml('Cargando personas…');
    try {
      const [users, cities] = await Promise.all([
        api('/usuarios/listar'),
        api('/ciudades/listar'),
      ]);
      lista = Array.isArray(users) ? users : [];
      ciudades = Array.isArray(cities) ? cities : [];
      const editUser = editId
        ? lista.find((u) => Number(u.id) === Number(editId)) || null
        : null;

      const filtrados =
        filtro === 'todos'
          ? lista
          : lista.filter((u) => String(u.rol) === filtro);

      root.innerHTML = `
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
          <div>
            <h1 class="h4 font-brand mb-1">Clientes y equipo</h1>
            <p class="text-secondary mb-0">Jugadores, operadores y administradores de tu organización.</p>
          </div>
          <a class="btn btn-outline-secondary btn-sm" href="#/mesa">Volver</a>
        </div>

        <div class="btn-group w-100 mb-3" role="group" aria-label="Filtro">
          <button type="button" class="btn ${filtro === 'jugador' ? 'btn-primary' : 'btn-outline-secondary'}" data-filtro="jugador">Clientes</button>
          <button type="button" class="btn ${filtro === 'operador' ? 'btn-primary' : 'btn-outline-secondary'}" data-filtro="operador">Operadores</button>
          <button type="button" class="btn ${filtro === 'administrador' ? 'btn-primary' : 'btn-outline-secondary'}" data-filtro="administrador">Admins</button>
          <button type="button" class="btn ${filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary'}" data-filtro="todos">Todos</button>
        </div>

        ${formClienteHtml(ciudades, editUser)}

        ${
          filtrados.length === 0
            ? emptyHtml('No hay personas en este filtro.')
            : `<div class="mesa-clientes-list">${filtrados.map((u) => cardClienteHtml(u, ciudades)).join('')}</div>`
        }`;

      root.querySelectorAll('[data-filtro]').forEach((btn) => {
        btn.addEventListener('click', () => {
          filtro = btn.getAttribute('data-filtro') || 'jugador';
          editId = null;
          paint();
        });
      });

      root.querySelectorAll('[data-editar]').forEach((btn) => {
        btn.addEventListener('click', () => {
          editId = Number(btn.getAttribute('data-editar'));
          paint();
          root.querySelector('#formCliente')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });

      root.querySelector('#btnCancelarEdit')?.addEventListener('click', () => {
        editId = null;
        paint();
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
          await paint();
        } catch (e) {
          toast(e.message || 'No se pudo guardar');
          setButtonBusy(btn, false);
        }
      });
    } catch (e) {
      toast(e.message || 'Error al cargar');
      root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
    }
  };

  await paint();
}
