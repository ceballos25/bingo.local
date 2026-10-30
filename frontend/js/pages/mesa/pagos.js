import { api } from '../../api.js';
import { requireMesa, esAdministrador } from '../../auth.js';
import {
  emptyHtml,
  escapeHtml,
  formatMoney,
  formatFecha,
  loadingHtml,
  renderCartonHtml,
  setButtonBusy,
  toast,
} from '../../ui.js';

function canalLabel(canal) {
  const map = {
    comprobante: 'Transferencia',
    openpay_pse: 'PSE',
    manual_operador: 'Venta manual',
    gratis: 'Gratis',
  };
  return map[String(canal || '')] || String(canal || '');
}

function estadoPagoBadge(estado) {
  const map = {
    pendiente: 'badge text-bg-warning',
    aprobado: 'badge text-bg-success',
    rechazado: 'badge text-bg-danger',
    expirado: 'badge text-bg-secondary',
  };
  const cls = map[estado] || 'badge text-bg-light border';
  return `<span class="${cls}">${escapeHtml(String(estado || ''))}</span>`;
}

function nombreCliente(p) {
  return `${p.clienteNombre || ''} ${p.clienteApellido || ''}`.trim() || 'Cliente';
}

function cardPagoHtml(p) {
  const nombre = escapeHtml(nombreCliente(p));
  const celular = escapeHtml(p.clienteCelular || '—');
  const correo = escapeHtml(p.clienteCorreo || '—');
  const partida = escapeHtml(p.partidaNombre || `Partida #${p.partidaId}`);
  const puedeDecidir =
    p.estado === 'pendiente' && p.canal === 'comprobante';
  const comprobante = p.comprobanteUrl
    ? `<a class="btn btn-outline-primary" href="${escapeHtml(p.comprobanteUrl)}" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-image me-1" aria-hidden="true"></i>Ver comprobante
      </a>`
    : '';
  const wa = p.whatsappUrl
    ? `<a class="btn btn-outline-success" href="${escapeHtml(p.whatsappUrl)}" target="_blank" rel="noopener noreferrer">
        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>WhatsApp al cliente
      </a>`
    : '';

  return `
    <article class="mesa-pago-card border bg-white" data-pago-id="${p.id}">
      <div class="mesa-pago-card__head">
        <div class="min-w-0">
          <h2 class="h5 font-brand mb-1">${nombre}</h2>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            ${estadoPagoBadge(p.estado)}
            <span class="badge text-bg-light border">${escapeHtml(canalLabel(p.canal))}</span>
          </div>
        </div>
        <div class="text-end flex-shrink-0">
          <div class="fs-5 fw-bold text-primary">${formatMoney(p.monto)}</div>
          <div class="small text-secondary">${escapeHtml(formatFecha(p.creadoEn))}</div>
        </div>
      </div>

      <div class="mesa-pago-card__cliente">
        <div>
          <div class="small text-secondary">Celular</div>
          <a class="fw-semibold text-decoration-none" href="tel:${celular}">${celular}</a>
        </div>
        <div class="min-w-0">
          <div class="small text-secondary">Correo</div>
          <a class="fw-semibold text-decoration-none text-break" href="mailto:${correo}">${correo}</a>
        </div>
        <div>
          <div class="small text-secondary">Bingo</div>
          <div class="fw-semibold">${partida}</div>
        </div>
      </div>

      <div class="mesa-pago-card__carton">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold">Cartón #${escapeHtml(String(p.cartonId))}</span>
          <span class="small text-secondary">${escapeHtml(String(p.cartonEstado || ''))}</span>
        </div>
        <div class="mesa-pago-card__grid">
          ${renderCartonHtml(p.cartonNumeros || {})}
        </div>
      </div>

      <div class="mesa-pago-card__actions">
        ${comprobante}
        ${wa}
        ${
          puedeDecidir
            ? `
          <button type="button" class="btn btn-primary" data-aprobar="${p.id}">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Aprobar · enviar correo
          </button>
          <button type="button" class="btn btn-outline-danger" data-rechazar="${p.id}">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Rechazar
          </button>`
            : ''
        }
      </div>
    </article>`;
}

function tabsHtml(tab) {
  return `
    <div class="btn-group w-100 mb-3" role="group" aria-label="Sección">
      <button type="button" class="btn ${tab === 'pagos' ? 'btn-primary' : 'btn-outline-secondary'}" data-tab="pagos">
        Pagos
      </button>
      <button type="button" class="btn ${tab === 'cuentas' ? 'btn-primary' : 'btn-outline-secondary'}" data-tab="cuentas">
        Cuentas transferencia
      </button>
    </div>`;
}

function cuentaCardHtml(c) {
  const activa = !!c.activo;
  return `
    <article class="mesa-cuenta-card border bg-white ${activa ? '' : 'mesa-cuenta-card--off'}" data-cuenta-id="${c.id}">
      <div class="d-flex justify-content-between gap-2 align-items-start">
        <div class="min-w-0">
          <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
            <h2 class="h6 font-brand mb-0">${escapeHtml(c.etiqueta || c.banco || 'Cuenta')}</h2>
            <span class="badge text-bg-light border text-uppercase">${escapeHtml(c.tipo || '')}</span>
            ${activa ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-secondary">Inactiva</span>'}
          </div>
          <div class="fw-semibold text-break">${escapeHtml(c.valor || '')}</div>
          <div class="small text-secondary">
            ${escapeHtml(c.banco || '')}${c.titular ? ' · ' + escapeHtml(c.titular) : ''}
            ${c.marca ? ' · ' + escapeHtml(c.marca) : ''}
          </div>
        </div>
        <div class="d-flex flex-column gap-1 flex-shrink-0">
          <button type="button" class="btn btn-sm btn-outline-primary" data-editar="${c.id}">Editar</button>
          ${
            activa
              ? `<button type="button" class="btn btn-sm btn-outline-danger" data-desactivar="${c.id}">Desactivar</button>`
              : `<button type="button" class="btn btn-sm btn-outline-success" data-activar="${c.id}">Activar</button>`
          }
        </div>
      </div>
    </article>`;
}

function formCuentaHtml(c = null) {
  const edit = c != null;
  return `
    <form class="mesa-cuenta-form border bg-white p-3 mb-3" id="formCuenta" data-edit-id="${edit ? c.id : ''}">
      <h2 class="h6 font-brand mb-3">${edit ? 'Editar cuenta' : 'Nueva cuenta de transferencia'}</h2>
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label small mb-1" for="cuentaTipo">Tipo</label>
          <select class="form-select" id="cuentaTipo" required>
            ${['nequi', 'bancolombia', 'breb', 'otro']
              .map(
                (t) =>
                  `<option value="${t}" ${edit && c.tipo === t ? 'selected' : ''}>${t}</option>`
              )
              .join('')}
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1" for="cuentaBanco">Banco / medio</label>
          <input class="form-control" id="cuentaBanco" required maxlength="80" value="${escapeHtml(edit ? c.banco || '' : '')}" placeholder="Nequi, Bancolombia…">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1" for="cuentaEtiqueta">Etiqueta visible</label>
          <input class="form-control" id="cuentaEtiqueta" required maxlength="80" value="${escapeHtml(edit ? c.etiqueta || '' : '')}" placeholder="Nequi CCM">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cuentaValor">Número / llave (copiar)</label>
          <input class="form-control" id="cuentaValor" required maxlength="120" value="${escapeHtml(edit ? c.valor || '' : '')}" placeholder="3001234567">
        </div>
        <div class="col-md-6">
          <label class="form-label small mb-1" for="cuentaTitular">Titular</label>
          <input class="form-control" id="cuentaTitular" maxlength="120" value="${escapeHtml(edit ? c.titular || '' : '')}" placeholder="Nombre del titular">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1" for="cuentaMarca">Marca / nota</label>
          <input class="form-control" id="cuentaMarca" maxlength="80" value="${escapeHtml(edit ? c.marca || '' : '')}" placeholder="Opcional">
        </div>
        <div class="col-md-4">
          <label class="form-label small mb-1" for="cuentaOrden">Orden</label>
          <input class="form-control" id="cuentaOrden" type="number" min="0" step="1" value="${edit ? Number(c.orden) || 0 : 0}">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="cuentaActivo" ${!edit || c.activo ? 'checked' : ''}>
            <label class="form-check-label" for="cuentaActivo">Activa (visible al pagar)</label>
          </div>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-primary" id="btnGuardarCuenta">
          ${edit ? 'Guardar cambios' : 'Crear cuenta'}
        </button>
        ${edit ? '<button type="button" class="btn btn-outline-secondary" id="btnCancelarEdit">Cancelar</button>' : ''}
      </div>
    </form>`;
}

export async function renderMesaPagos(root) {
  if (!requireMesa()) {
    root.innerHTML = emptyHtml('Solo operadores y administradores.');
    return;
  }

  let tab = /[?&]tab=cuentas/.test(location.hash || '') ? 'cuentas' : 'pagos';
  let filtro = 'pendiente';
  let editCuenta = null;

  const paint = async () => {
    root.innerHTML = loadingHtml(tab === 'cuentas' ? 'Cargando cuentas…' : 'Cargando pagos…');
    try {
      if (tab === 'cuentas') {
        await paintCuentas();
        return;
      }
      await paintPagos();
    } catch (e) {
      toast(e.message || 'Error al cargar');
      root.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(e.message)}</div>`;
    }
  };

  const wireTabs = () => {
    root.querySelectorAll('[data-tab]').forEach((btn) => {
      btn.addEventListener('click', () => {
        tab = btn.getAttribute('data-tab') || 'pagos';
        editCuenta = null;
        paint();
      });
    });
  };

  const paintPagos = async () => {
    const qs =
      filtro && filtro !== 'todos'
        ? `?estado=${encodeURIComponent(filtro)}`
        : '';
    const data = await api('/ventas/listar' + qs);
    const pagos = Array.isArray(data?.pagos) ? data.pagos : [];

    root.innerHTML = `
      <h1 class="h4 font-brand mb-1">Pagos y cuentas</h1>
      <p class="text-secondary mb-3">
        Revisa compras y configura a qué cuentas transfieren tus clientes.
      </p>
      ${tabsHtml('pagos')}

      <div class="btn-group w-100 mb-3" role="group" aria-label="Filtro de pagos">
        <button type="button" class="btn ${filtro === 'pendiente' ? 'btn-warning' : 'btn-outline-secondary'}" data-filtro="pendiente">
          Por revisar
        </button>
        <button type="button" class="btn ${filtro === 'aprobado' ? 'btn-success' : 'btn-outline-secondary'}" data-filtro="aprobado">
          Aprobados
        </button>
        <button type="button" class="btn ${filtro === 'todos' ? 'btn-primary' : 'btn-outline-secondary'}" data-filtro="todos">
          Todos
        </button>
      </div>

      ${
        pagos.length === 0
          ? emptyHtml(
              filtro === 'pendiente'
                ? 'No hay pagos por revisar.'
                : 'No hay pagos en este filtro.'
            )
          : `<div class="mesa-pagos-list">${pagos.map(cardPagoHtml).join('')}</div>`
      }`;

    wireTabs();

    root.querySelectorAll('[data-filtro]').forEach((btn) => {
      btn.addEventListener('click', () => {
        filtro = btn.getAttribute('data-filtro') || 'pendiente';
        paint();
      });
    });

    root.querySelectorAll('[data-aprobar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const pagoId = Number(btn.getAttribute('data-aprobar'));
        if (!pagoId) return;
        if (
          !confirm(
            '¿Aprobar este pago? El cliente recibirá sus cartones por correo.'
          )
        ) {
          return;
        }
        setButtonBusy(btn, true, 'Aprobando…');
        try {
          await api('/ventas/aprobar-comprobante', {
            method: 'POST',
            body: { pagoId },
          });
          toast('Pago aprobado. Correo enviado al cliente.', 'success');
          await paint();
        } catch (e) {
          toast(e.message || 'No se pudo aprobar');
          setButtonBusy(btn, false);
        }
      });
    });

    root.querySelectorAll('[data-rechazar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const pagoId = Number(btn.getAttribute('data-rechazar'));
        if (!pagoId) return;
        if (!confirm('¿Rechazar este comprobante? El cartón se libera.')) {
          return;
        }
        setButtonBusy(btn, true, 'Rechazando…');
        try {
          await api('/ventas/rechazar-comprobante', {
            method: 'POST',
            body: { pagoId },
          });
          toast('Comprobante rechazado', 'warning');
          await paint();
        } catch (e) {
          toast(e.message || 'No se pudo rechazar');
          setButtonBusy(btn, false);
        }
      });
    });
  };

  const paintCuentas = async () => {
    if (!esAdministrador()) {
      root.innerHTML = `
        <h1 class="h4 font-brand mb-1">Pagos y cuentas</h1>
        ${tabsHtml('cuentas')}
        ${emptyHtml('Solo el administrador puede gestionar las cuentas de transferencia.')}`;
      wireTabs();
      return;
    }

    const cuentas = await api('/cuentas-pago/listar');
    const list = Array.isArray(cuentas) ? cuentas : [];

    root.innerHTML = `
      <h1 class="h4 font-brand mb-1">Pagos y cuentas</h1>
      <p class="text-secondary mb-3">
        Estas cuentas aparecen cuando el jugador paga por transferencia.
      </p>
      ${tabsHtml('cuentas')}
      ${formCuentaHtml(editCuenta)}
      ${
        list.length === 0
          ? emptyHtml('Aún no hay cuentas. Crea la primera arriba.')
          : `<div class="mesa-cuentas-list">${list.map(cuentaCardHtml).join('')}</div>`
      }`;

    wireTabs();

    const form = root.querySelector('#formCuenta');
    form?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = root.querySelector('#btnGuardarCuenta');
      const payload = {
        tipo: root.querySelector('#cuentaTipo')?.value || 'otro',
        banco: root.querySelector('#cuentaBanco')?.value?.trim() || '',
        etiqueta: root.querySelector('#cuentaEtiqueta')?.value?.trim() || '',
        valor: root.querySelector('#cuentaValor')?.value?.trim() || '',
        titular: root.querySelector('#cuentaTitular')?.value?.trim() || '',
        marca: root.querySelector('#cuentaMarca')?.value?.trim() || '',
        orden: Number(root.querySelector('#cuentaOrden')?.value) || 0,
        activo: !!root.querySelector('#cuentaActivo')?.checked,
      };
      const editId = Number(form.getAttribute('data-edit-id') || 0);
      setButtonBusy(btn, true, 'Guardando…');
      try {
        if (editId > 0) {
          await api('/cuentas-pago/actualizar?id=' + editId, {
            method: 'PUT',
            body: payload,
          });
          toast('Cuenta actualizada', 'success');
        } else {
          await api('/cuentas-pago/crear', {
            method: 'POST',
            body: payload,
          });
          toast('Cuenta creada', 'success');
        }
        editCuenta = null;
        await paint();
      } catch (e) {
        toast(e.message || 'No se pudo guardar');
        setButtonBusy(btn, false);
      }
    });

    root.querySelector('#btnCancelarEdit')?.addEventListener('click', () => {
      editCuenta = null;
      paint();
    });

    root.querySelectorAll('[data-editar]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.getAttribute('data-editar'));
        editCuenta = list.find((c) => Number(c.id) === id) || null;
        paint();
      });
    });

    root.querySelectorAll('[data-desactivar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = Number(btn.getAttribute('data-desactivar'));
        if (!id || !confirm('¿Desactivar esta cuenta? Dejará de verse en el checkout.')) {
          return;
        }
        setButtonBusy(btn, true, '…');
        try {
          await api('/cuentas-pago/eliminar?id=' + id, { method: 'DELETE' });
          toast('Cuenta desactivada', 'warning');
          await paint();
        } catch (e) {
          toast(e.message || 'No se pudo desactivar');
          setButtonBusy(btn, false);
        }
      });
    });

    root.querySelectorAll('[data-activar]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = Number(btn.getAttribute('data-activar'));
        if (!id) return;
        setButtonBusy(btn, true, '…');
        try {
          await api('/cuentas-pago/actualizar?id=' + id, {
            method: 'PUT',
            body: { activo: true },
          });
          toast('Cuenta activada', 'success');
          await paint();
        } catch (e) {
          toast(e.message || 'No se pudo activar');
          setButtonBusy(btn, false);
        }
      });
    });
  };

  await paint();
}
