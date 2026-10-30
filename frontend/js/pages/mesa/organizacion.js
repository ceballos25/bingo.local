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

export async function renderMesaOrganizacion(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  const yo = getUsuario() || {};
  const orgId = Number(yo.organizacionId);
  if (!orgId) {
    root.innerHTML = emptyHtml('No hay organización en la sesión.');
    return;
  }

  root.innerHTML = loadingHtml('Cargando organización…');

  try {
    const org = await api('/organizaciones/buscar?id=' + encodeURIComponent(orgId));
    const puedeEditar = esAdministrador();

    root.innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <h1 class="h4 font-brand mb-1">Organización</h1>
          <p class="text-secondary mb-0">Datos de tu bingo / empresa en la plataforma.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="#/mesa">Volver</a>
      </div>

      <form class="border bg-white p-3 mesa-org-form" id="formOrg">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label" for="orgNombre">Nombre</label>
            <input class="form-control form-control-lg" id="orgNombre" required maxlength="120"
              value="${escapeHtml(org.nombre || '')}" ${puedeEditar ? '' : 'readonly'}>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="orgEstado">Estado</label>
            <select class="form-select form-select-lg" id="orgEstado" ${puedeEditar ? '' : 'disabled'}>
              <option value="activo" ${org.estado === 'activo' ? 'selected' : ''}>activo</option>
              <option value="inactivo" ${org.estado === 'inactivo' ? 'selected' : ''}>inactivo</option>
            </select>
          </div>
          <div class="col-12">
            <dl class="row small text-secondary mb-0">
              <dt class="col-sm-3">ID</dt>
              <dd class="col-sm-9">${escapeHtml(String(org.id))}</dd>
              <dt class="col-sm-3">Creada</dt>
              <dd class="col-sm-9">${escapeHtml(formatFecha(org.creadoEn))}</dd>
              <dt class="col-sm-3">Modificada</dt>
              <dd class="col-sm-9">${escapeHtml(formatFecha(org.modificadoEn))}</dd>
            </dl>
          </div>
        </div>
        ${
          puedeEditar
            ? `<div class="mt-3">
                <button type="submit" class="btn btn-primary" id="btnGuardarOrg">Guardar cambios</button>
              </div>`
            : `<p class="small text-secondary mt-3 mb-0">Solo el administrador puede editar estos datos.</p>`
        }
      </form>

      <div class="border bg-white p-3 mt-3">
        <h2 class="h6 font-brand mb-2">Alta de nuevas organizaciones</h2>
        <p class="small text-secondary mb-0">
          Crear otros tenants (SaaS) queda para más adelante. Hoy cada mesa opera su propia organización.
        </p>
      </div>`;

    if (!puedeEditar) return;

    root.querySelector('#formOrg')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = root.querySelector('#btnGuardarOrg');
      const nombre = root.querySelector('#orgNombre')?.value?.trim() || '';
      const estado = root.querySelector('#orgEstado')?.value || 'activo';
      if (!nombre) {
        toast('El nombre es obligatorio');
        return;
      }
      setButtonBusy(btn, true, 'Guardando…');
      try {
        const actualizada = await api(
          '/organizaciones/actualizar?id=' + encodeURIComponent(orgId),
          {
            method: 'PUT',
            body: { nombre, estado },
          }
        );
        if (yo && actualizada?.nombre) {
          setUsuario({ ...yo, organizacionNombre: actualizada.nombre });
        }
        toast('Organización actualizada', 'success');
        await renderMesaOrganizacion(root);
      } catch (e) {
        toast(e.message || 'No se pudo guardar');
        setButtonBusy(btn, false);
      }
    });
  } catch (e) {
    toast(e.message || 'Error al cargar');
    root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
  }
}
