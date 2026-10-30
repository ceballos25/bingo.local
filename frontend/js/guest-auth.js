/**
 * Identidad de jugador solo al pagar: modal global → JWT en localStorage.
 */
import { api } from './api.js';
import { isLoggedIn, setSession } from './auth.js';
import { requireOrganizacionId } from './org-context.js';
import { CONFIG } from './config.js';
import { escapeHtml, otpInputsHtml, setButtonBusy, toast, wireOtpInputs } from './ui.js';

let pendingResolve = null;
let wired = false;

function opcionesCiudad(ciudades) {
  return (ciudades || [])
    .map(
      (c) =>
        `<option value="${c.id}">${escapeHtml(c.nombre || c.ciudad || String(c.id))}</option>`
    )
    .join('');
}

function wireSoloDigitos(input) {
  if (!input) return;
  input.addEventListener('input', () => {
    input.value = String(input.value || '').replace(/\D/g, '').slice(0, 10);
  });
}

function initCiudadSelect(selectEl, modalEl) {
  if (!selectEl || typeof window.jQuery === 'undefined' || !window.jQuery.fn?.select2) {
    return;
  }
  const $ = window.jQuery;
  const $el = $(selectEl);
  if ($el.hasClass('select2-hidden-accessible')) {
    $el.select2('destroy');
  }
  $el.select2({
    theme: 'bootstrap-5',
    width: '100%',
    placeholder: 'Buscar ciudad…',
    allowClear: false,
    dropdownParent: modalEl ? $(modalEl) : $el.parent(),
    language: {
      noResults: () => 'Sin resultados',
      searching: () => 'Buscando…',
    },
  });
}

function modalHtml(ciudades) {
  const opts = opcionesCiudad(ciudades);
  return `
    <div class="modal fade" id="guestCheckoutModal" tabindex="-1" aria-labelledby="guestCheckoutLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <div>
              <h2 class="modal-title h5 font-brand mb-1" id="guestCheckoutLabel">Tus datos para pagar</h2>
              <p class="small text-secondary mb-0">Los pedimos una sola vez. En este celular te los recordamos.</p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body pt-3">
            <div id="guestCheckoutNuevo">
              <form id="guestCheckoutFormNuevo">
                <div class="row g-2">
                  <div class="col-6">
                    <div class="form-floating">
                      <input name="nombre" class="form-control" id="gcoNombre" placeholder="Nombre" required autocomplete="given-name">
                      <label for="gcoNombre">Nombre</label>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-floating">
                      <input name="apellido" class="form-control" id="gcoApellido" placeholder="Apellido" required autocomplete="family-name">
                      <label for="gcoApellido">Apellido</label>
                    </div>
                  </div>
                </div>
                <div class="form-floating mt-2">
                  <input name="celular" class="form-control" id="gcoCel" placeholder="Celular" required minlength="10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" autocomplete="tel" title="10 dígitos">
                  <label for="gcoCel">Celular</label>
                </div>
                <div class="form-floating mt-2">
                  <input name="correo" type="email" class="form-control" id="gcoMail" placeholder="Correo" required inputmode="email" autocomplete="email">
                  <label for="gcoMail">Correo</label>
                </div>
                <div class="mt-2 mb-3">
                  <label for="gcoCiudad" class="form-label">Ciudad</label>
                  <select name="ciudadId" class="form-select" id="gcoCiudad" required>
                    <option value=""></option>
                    ${opts}
                  </select>
                </div>
                <button class="btn btn-success w-100 guest-main-btn" type="submit" id="gcoSubmitNuevo">
                  <i class="bi bi-credit-card" aria-hidden="true"></i>Ir a pagar
                </button>
              </form>
              <p class="small text-secondary text-center mt-3 mb-2">¿Ya compraste aquí antes?</p>
              <button type="button" class="btn btn-outline-primary w-100 guest-switch-btn" id="gcoShowReturning">
                <i class="bi bi-person-check" aria-hidden="true"></i>Sí, ya compré aquí
              </button>
            </div>
            <div id="guestCheckoutReturning" class="d-none">
              <p class="small text-secondary mb-3">Escribe el celular y el correo que usaste la vez pasada.</p>
              <form id="guestCheckoutFormReturning">
                <div class="form-floating mb-2">
                  <input name="celular" class="form-control" id="gcoRetCel" placeholder="Celular" required minlength="10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" autocomplete="tel" title="10 dígitos">
                  <label for="gcoRetCel">Celular</label>
                </div>
                <div class="form-floating mb-3">
                  <input name="correo" type="email" class="form-control" id="gcoRetMail" placeholder="Correo" required inputmode="email" autocomplete="email">
                  <label for="gcoRetMail">Correo</label>
                </div>
                <button class="btn btn-success w-100 guest-main-btn" type="submit" id="gcoSubmitReturning">
                  <i class="bi bi-credit-card" aria-hidden="true"></i>Ir a pagar
                </button>
              </form>
              <p class="small text-secondary text-center mt-3 mb-2">¿Eres nuevo aquí?</p>
              <button type="button" class="btn btn-outline-primary w-100 guest-switch-btn" id="gcoShowNuevo">
                <i class="bi bi-person-plus" aria-hidden="true"></i>Sí, soy nuevo aquí
              </button>
            </div>
            <div id="guestCheckoutOtp" class="d-none">
              <p class="small text-secondary mb-2 text-center">Te enviamos un código de 6 números a<br><strong id="gcoOtpMail"></strong></p>
              <form id="guestCheckoutFormOtp">
                ${otpInputsHtml('gcoOtp')}
                <button class="btn btn-success w-100 guest-main-btn" type="submit" id="gcoOtpSubmit">
                  <i class="bi bi-shield-check" aria-hidden="true"></i>Verificar y pagar
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>`;
}

function finishOk(token, usuario) {
  setSession(token, usuario);
  toast('Listo: puedes pagar', 'success');
  if (pendingResolve) {
    const resolve = pendingResolve;
    pendingResolve = null;
    resolve(true);
  }
  const modalEl = document.getElementById('guestCheckoutModal');
  const modal = modalEl && window.bootstrap?.Modal.getInstance(modalEl);
  if (modal) modal.hide();
}

function finishCancel() {
  if (pendingResolve) {
    const resolve = pendingResolve;
    pendingResolve = null;
    resolve(false);
  }
}

async function ensureModal() {
  let modalEl = document.getElementById('guestCheckoutModal');
  if (!modalEl) {
    let ciudades = [];
    try {
      ciudades = (await api('/ciudades/listar')) || [];
    } catch (_) {
      ciudades = [];
    }
    document.body.insertAdjacentHTML('beforeend', modalHtml(ciudades));
    modalEl = document.getElementById('guestCheckoutModal');
  }

  if (!wired && modalEl) {
    wired = true;
    let otpCorreo = '';
    const otpCtrl = wireOtpInputs(modalEl.querySelector('#guestCheckoutFormOtp') || modalEl);
    const showPane = (which) => {
      modalEl.querySelector('#guestCheckoutNuevo')?.classList.toggle('d-none', which !== 'nuevo');
      modalEl.querySelector('#guestCheckoutReturning')?.classList.toggle('d-none', which !== 'returning');
      modalEl.querySelector('#guestCheckoutOtp')?.classList.toggle('d-none', which !== 'otp');
    };
    const showOtp = (correo) => {
      otpCorreo = correo;
      const label = modalEl.querySelector('#gcoOtpMail');
      if (label) label.textContent = correo;
      showPane('otp');
      otpCtrl.clear();
      otpCtrl.focus();
      toast('Te enviamos un código al correo', 'success');
    };
    const handleRes = (res, btn) => {
      if (res?.requiereOtp) {
        setButtonBusy(btn, false);
        showOtp(res.correo || otpCorreo);
        return;
      }
      finishOk(res.token, res.usuario);
    };

    modalEl.querySelector('#gcoShowReturning')?.addEventListener('click', () =>
      showPane('returning')
    );
    modalEl.querySelector('#gcoShowNuevo')?.addEventListener('click', () =>
      showPane('nuevo')
    );
    wireSoloDigitos(modalEl.querySelector('#gcoCel'));
    wireSoloDigitos(modalEl.querySelector('#gcoRetCel'));

    modalEl.addEventListener('shown.bs.modal', async () => {
      const select = modalEl.querySelector('#gcoCiudad');
      if (select && select.options.length <= 1) {
        try {
          const ciudades = (await api('/ciudades/listar')) || [];
          select.insertAdjacentHTML('beforeend', opcionesCiudad(ciudades));
        } catch (_) {
          toast('No se pudieron cargar las ciudades. Intenta de nuevo.', 'warning');
        }
      }
      initCiudadSelect(select, modalEl);
      showPane('nuevo');
    });
    modalEl.addEventListener('hidden.bs.modal', () => finishCancel());

    modalEl.querySelector('#guestCheckoutFormNuevo')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const form = ev.target;
      const cel = form.querySelector('[name="celular"]');
      if (cel) cel.value = String(cel.value || '').replace(/\D/g, '').slice(0, 10);
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      const btn = modalEl.querySelector('#gcoSubmitNuevo');
      const fd = new FormData(form);
      const celular = String(fd.get('celular') || '').replace(/\D/g, '');
      const correo = String(fd.get('correo') || '').trim();
      if (!/^[0-9]{10}$/.test(celular)) {
        toast('El celular debe tener exactamente 10 dígitos', 'warning');
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
        toast('Ingresa un correo válido', 'warning');
        return;
      }
      setButtonBusy(btn, true, 'Guardando…');
      try {
        const res = await api('/auth/invitado', {
          method: 'POST',
          body: {
            organizacionId: requireOrganizacionId(),
            paraPagar: true,
            ciudadId: Number(fd.get('ciudadId')) || CONFIG.ciudadIdDefault || 1,
            nombre: String(fd.get('nombre')),
            apellido: String(fd.get('apellido')),
            celular,
            correo,
          },
        });
        handleRes(res, btn);
      } catch (e) {
        toast(e.message || 'No se pudo continuar');
        setButtonBusy(btn, false);
      }
    });

    modalEl
      .querySelector('#guestCheckoutFormReturning')
      ?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const form = ev.target;
        const cel = form.querySelector('[name="celular"]');
        if (cel) cel.value = String(cel.value || '').replace(/\D/g, '').slice(0, 10);
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        const btn = modalEl.querySelector('#gcoSubmitReturning');
        const fd = new FormData(form);
        const celular = String(fd.get('celular') || '').replace(/\D/g, '');
        const correo = String(fd.get('correo') || '').trim();
        if (!/^[0-9]{10}$/.test(celular)) {
          toast('El celular debe tener exactamente 10 dígitos', 'warning');
          return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
          toast('Ingresa un correo válido', 'warning');
          return;
        }
        setButtonBusy(btn, true, 'Entrando…');
        try {
          const res = await api('/auth/acceso-invitado', {
            method: 'POST',
            body: {
              organizacionId: requireOrganizacionId(),
              paraPagar: true,
              celular,
              correo,
            },
          });
          handleRes(res, btn);
        } catch (e) {
          toast(e.message || 'No se pudo continuar');
          setButtonBusy(btn, false);
        }
      });

    modalEl.querySelector('#guestCheckoutFormOtp')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const codigo = otpCtrl.getCodigo();
      if (!/^[0-9]{6}$/.test(codigo)) {
        toast('Ingresa el código de 6 dígitos', 'warning');
        otpCtrl.focus();
        return;
      }
      const btn = modalEl.querySelector('#gcoOtpSubmit');
      setButtonBusy(btn, true, 'Verificando…');
      try {
        const res = await api('/auth/otp-verificar', {
          method: 'POST',
          body: {
            organizacionId: requireOrganizacionId(),
            correo: otpCorreo,
            codigo,
          },
        });
        finishOk(res.token, res.usuario);
      } catch (e) {
        toast(e.message || 'Código inválido');
        otpCtrl.clear();
        otpCtrl.focus();
        setButtonBusy(btn, false);
      }
    });
  }

  return modalEl;
}

/**
 * Si no hay sesión, pide datos y resuelve true/false.
 * Si ya hay sesión, true de inmediato.
 */
export async function ensureGuestSession() {
  if (isLoggedIn()) return true;

  const modalEl = await ensureModal();
  if (!modalEl || !window.bootstrap?.Modal) {
    toast('No se pudo abrir el formulario de datos', 'warning');
    return false;
  }

  return new Promise((resolve) => {
    pendingResolve = resolve;
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  });
}
