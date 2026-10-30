import { api } from '../api.js';
import { CONFIG } from '../config.js';
import { requireOrganizacionId } from '../org-context.js';
import {
  clearSession,
  consumeAfterLogin,
  getToken,
  getUsuario,
  homeHashForUser,
  isLoggedIn,
  peekAfterLogin,
  setSession,
  setUsuario,
} from '../auth.js';
import { activarNotificaciones, instalarPwa, puedeInstalarPwa } from '../push.js';
import {
  escapeHtml,
  toast,
  setButtonBusy,
  inicialesUsuario,
  colorAvatarUsuario,
  otpInputsHtml,
  wireOtpInputs,
} from '../ui.js';

function supportLinksHtml() {
  const wa = CONFIG.support?.whatsapp
    ? `https://wa.me/${CONFIG.support.whatsapp}?text=${encodeURIComponent('Hola, necesito ayuda con mi bingo / cartón.')}`
    : '#';
  const mail = CONFIG.support?.email ? `mailto:${CONFIG.support.email}` : '#';

  return `
    <div class="border bg-white px-3 py-2 mb-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="small text-secondary fw-semibold"><i class="bi bi-headset me-1" aria-hidden="true"></i>Soporte</span>
        <div class="profile-support">
          <button type="button" class="footer-chip footer-chip--help" data-bs-toggle="modal" data-bs-target="#ayudaModal">
            <i class="bi bi-question-circle-fill" aria-hidden="true"></i>
            <span>Ayuda</span>
          </button>
          <a class="footer-chip footer-chip--wa" href="${escapeHtml(wa)}" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
            <span>WhatsApp</span>
          </a>
          <a class="footer-chip footer-chip--mail" href="${escapeHtml(mail)}">
            <i class="bi bi-envelope-fill" aria-hidden="true"></i>
            <span>Contacto</span>
          </a>
        </div>
      </div>
    </div>`;
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

/** Select2 con buscador (tema Bootstrap 5) en el campo ciudad. */
function initCiudadSelect(selectEl) {
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
    language: {
      noResults: () => 'Sin resultados',
      searching: () => 'Buscando…',
      inputTooShort: () => 'Escribe para buscar',
    },
    dropdownParent: $el.closest('.tab-pane, .border, form, body').first(),
  });
}

function bindPerfilActions(root, onSaved) {
  root.querySelector('#btnNotif')?.addEventListener('click', async () => {
    try {
      await activarNotificaciones();
      toast('Notificaciones activadas', 'success');
    } catch (e) {
      toast(e.message || 'No se pudieron activar');
    }
  });

  root.querySelector('#btnInstall')?.addEventListener('click', async () => {
    try {
      if (puedeInstalarPwa()) {
        await instalarPwa();
        toast('App instalada', 'success');
      } else {
        toast(
          'Usa el menú del navegador para instalar, o en iPhone “Añadir a Inicio”.',
          'warning'
        );
      }
    } catch (e) {
      toast(e.message);
    }
  });

  root.querySelector('#btnOut')?.addEventListener('click', () => {
    clearSession();
    location.hash = '#/cuenta';
    location.reload();
  });

  const vista = root.querySelector('#perfilVista');
  const edicion = root.querySelector('#perfilEdicion');
  const abrirEdicion = () => {
    vista?.classList.add('d-none');
    edicion?.classList.remove('d-none');
    initCiudadSelect(root.querySelector('#perfilCiudad'));
    root.querySelector('#perfilNombre')?.focus();
  };
  const cerrarEdicion = () => {
    edicion?.classList.add('d-none');
    vista?.classList.remove('d-none');
  };

  root.querySelector('#btnEditarPerfil')?.addEventListener('click', abrirEdicion);
  root.querySelector('#btnCancelarPerfil')?.addEventListener('click', cerrarEdicion);

  root.querySelector('#formPerfil')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.target;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const fd = new FormData(form);
    const btn = root.querySelector('#btnGuardarPerfil');
    const password = String(fd.get('password') || '').trim();
    // correo/celular son llave de acceso: el backend los ignora para jugadores
    const body = {
      nombre: String(fd.get('nombre') || '').trim(),
      apellido: String(fd.get('apellido') || '').trim(),
      ciudadId: Number(fd.get('ciudadId')),
    };
    if (password) body.password = password;

    if (btn) btn.disabled = true;
    try {
      const u = getUsuario() || {};
      const actualizado = await api('/usuarios/actualizar?id=' + encodeURIComponent(u.id), {
        method: 'PUT',
        body,
      });
      setUsuario(actualizado);
      toast('Perfil actualizado', 'success');
      onSaved?.(actualizado);
    } catch (e) {
      toast(e.message || 'No se pudo guardar');
    } finally {
      if (btn) btn.disabled = false;
    }
  });
}

function renderPerfilHtml(u, ciudades) {
  const iniciales = inicialesUsuario(u);
  const color = colorAvatarUsuario(u);
  const ciudad = ciudadNombre(ciudades, u.ciudadId);

  return `
    <h1 class="h4 mb-3 font-brand">Mi perfil</h1>

    <div class="border bg-white p-3 mb-3 profile-card">
      <div id="perfilVista">
        <div class="d-flex align-items-start gap-3 mb-3">
          <span class="user-avatar user-avatar--lg flex-shrink-0" style="background-color:${color}" aria-hidden="true">${escapeHtml(iniciales)}</span>
          <div class="min-w-0 flex-grow-1">
            <div class="fw-semibold text-break">${escapeHtml(u.nombre || '')} ${escapeHtml(u.apellido || '')}</div>
            <div class="text-secondary small profile-email" title="${escapeHtml(u.correo || '')}">${escapeHtml(u.correo || '')}</div>
            <div class="d-flex flex-wrap gap-1 mt-2">
              ${u.rol ? `<span class="badge text-bg-primary">${escapeHtml(u.rol)}</span>` : ''}
              <span class="badge text-bg-light border text-secondary"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>${escapeHtml(ciudad)}</span>
            </div>
          </div>
        </div>
        <dl class="profile-meta row g-2 mb-3 small">
          <div class="col-sm-6">
            <dt class="text-secondary mb-0">Celular</dt>
            <dd class="mb-0 fw-semibold">${escapeHtml(u.celular || '—')}</dd>
          </div>
          <div class="col-sm-6">
            <dt class="text-secondary mb-0">Ciudad</dt>
            <dd class="mb-0 fw-semibold">${escapeHtml(ciudad)}</dd>
          </div>
        </dl>
        <button type="button" class="btn btn-outline-primary w-100" id="btnEditarPerfil">
          <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
        </button>
      </div>

      <div id="perfilEdicion" class="d-none">
        <div class="fw-semibold mb-3"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar datos</div>
        <form id="formPerfil">
          <div class="row g-2">
            <div class="col-6">
              <div class="form-floating">
                <input name="nombre" class="form-control" id="perfilNombre" placeholder="Nombre" required value="${escapeHtml(u.nombre || '')}">
                <label for="perfilNombre">Nombre</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-floating">
                <input name="apellido" class="form-control" id="perfilApellido" placeholder="Apellido" required value="${escapeHtml(u.apellido || '')}">
                <label for="perfilApellido">Apellido</label>
              </div>
            </div>
          </div>
          <div class="mt-2">
            <label for="perfilCiudad" class="form-label">Ciudad</label>
            <select name="ciudadId" class="form-select" id="perfilCiudad" required data-ciudad-select>
              <option value="" disabled>Selecciona…</option>
              ${opcionesCiudad(ciudades, u.ciudadId)}
            </select>
          </div>
          <div class="form-floating mt-2 mb-3">
            <input name="password" type="password" class="form-control" id="perfilPass" placeholder="Nueva contraseña" minlength="6" autocomplete="new-password">
            <label for="perfilPass">Nueva contraseña (opcional)</label>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit" id="btnGuardarPerfil">
              <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Guardar
            </button>
            <button class="btn btn-outline-secondary" type="button" id="btnCancelarPerfil">Cancelar</button>
          </div>
        </form>
      </div>
    </div>

    ${supportLinksHtml()}

    <div class="list-group mb-3">
      <button type="button" class="list-group-item list-group-item-action" id="btnNotif">
        <i class="bi bi-bell me-2"></i>Activar notificaciones
      </button>
      <button type="button" class="list-group-item list-group-item-action" id="btnInstall">
        <i class="bi bi-download me-2"></i>Instalar aplicación
      </button>
      <button type="button" class="list-group-item list-group-item-action text-danger" id="btnOut">
        <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
      </button>
    </div>
    <p class="small text-secondary mb-0">
      En iPhone: Safari → Compartir → <strong>Añadir a Inicio</strong>, luego activa notificaciones desde la app instalada.
    </p>`;
}

export async function renderCuenta(root) {
  if (isLoggedIn()) {
    root.innerHTML = `
      <div class="d-flex justify-content-center align-items-center py-5 text-secondary gap-2">
        <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
        <span>Cargando perfil…</span>
      </div>`;

    let u = getUsuario() || {};
    let ciudades = [];
    try {
      const [yo, lista] = await Promise.all([
        api('/auth/yo'),
        api('/ciudades/listar'),
      ]);
      u = yo || u;
      setUsuario(u);
      ciudades = lista || [];
    } catch (e) {
      try {
        ciudades = await api('/ciudades/listar');
      } catch (e2) {
        ciudades = [];
      }
      if (!getToken()) {
        location.hash = '#/cuenta';
        return;
      }
    }

    const paint = (usuario) => {
      root.innerHTML = renderPerfilHtml(usuario, ciudades);
      bindPerfilActions(root, (nuevo) => {
        paint(nuevo);
        // Refresca avatar/nombre del header
        window.dispatchEvent(new HashChangeEvent('hashchange'));
      });
    };

    paint(u);
    return;
  }

  let ciudades = [];
  try {
    ciudades = await api('/ciudades/listar');
  } catch (e) {
    ciudades = [];
  }

  const opts = opcionesCiudad(ciudades, null);
  const afterBuy = peekAfterLogin();
  const wantReg = !!afterBuy;

  const guestFormsHtml = `
    <div id="paneDatos">
      <div id="paneNuevo">
        <form id="formGuest">
          <div class="row g-2">
            <div class="col-6">
              <div class="form-floating">
                <input name="nombre" class="form-control" id="guestNombre" placeholder="Nombre" required autocomplete="given-name">
                <label for="guestNombre">Nombre</label>
              </div>
            </div>
            <div class="col-6">
              <div class="form-floating">
                <input name="apellido" class="form-control" id="guestApellido" placeholder="Apellido" required autocomplete="family-name">
                <label for="guestApellido">Apellido</label>
              </div>
            </div>
          </div>
          <div class="form-floating mt-2">
            <input name="celular" class="form-control" id="guestCel" placeholder="Celular" required minlength="10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" autocomplete="tel" title="10 dígitos">
            <label for="guestCel">Celular</label>
          </div>
          <div class="form-floating mt-2">
            <input name="correo" type="email" class="form-control" id="guestMail" placeholder="Correo" required autocomplete="email" inputmode="email">
            <label for="guestMail">Correo</label>
          </div>
          <div class="mt-2 mb-3">
            <label for="guestCiudad" class="form-label">Ciudad</label>
            <select name="ciudadId" class="form-select" id="guestCiudad" required data-ciudad-select>
              <option value=""></option>
              ${opts}
            </select>
          </div>
          <button class="btn btn-success w-100 guest-main-btn" type="submit" id="guestSubmit">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Entrar
          </button>
        </form>
        <p class="small text-secondary text-center mt-3 mb-2">¿Ya compraste aquí antes?</p>
        <button type="button" class="btn btn-outline-primary w-100 guest-switch-btn" id="showReturning">
          <i class="bi bi-person-check" aria-hidden="true"></i>Sí, ya compré aquí
        </button>
      </div>
      <div id="paneReturning" class="d-none">
        <p class="small text-secondary mb-3">Escribe el celular y el correo que usaste la vez pasada.</p>
        <form id="formReturning">
          <div class="form-floating mb-2">
            <input name="celular" class="form-control" id="returningCel" placeholder="Celular" required minlength="10" maxlength="10" pattern="[0-9]{10}" inputmode="numeric" autocomplete="tel" title="10 dígitos">
            <label for="returningCel">Celular</label>
          </div>
          <div class="form-floating mb-3">
            <input name="correo" type="email" class="form-control" id="returningMail" placeholder="Correo" required inputmode="email" autocomplete="email">
            <label for="returningMail">Correo</label>
          </div>
          <button class="btn btn-success w-100 guest-main-btn" type="submit" id="returningSubmit">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>Entrar
          </button>
        </form>
        <p class="small text-secondary text-center mt-3 mb-2">¿Eres nuevo aquí?</p>
        <button type="button" class="btn btn-outline-primary w-100 guest-switch-btn" id="showNuevo">
          <i class="bi bi-person-plus" aria-hidden="true"></i>Sí, soy nuevo aquí
        </button>
      </div>
    </div>
    <div id="paneOtp" class="d-none">
      <div class="text-center mb-3">
        <i class="bi bi-envelope-check display-6 text-primary" aria-hidden="true"></i>
        <p class="fw-semibold mb-1 mt-2">Revisa tu correo</p>
        <p class="small text-secondary mb-0">Te enviamos un código de 6 números a<br><strong id="otpCorreoLabel"></strong></p>
      </div>
      <form id="formOtpVerificar">
        ${otpInputsHtml('otp')}
        <button class="btn btn-success w-100 guest-main-btn" type="submit" id="otpVerificarBtn">
          <i class="bi bi-shield-check" aria-hidden="true"></i>Verificar código
        </button>
      </form>
      <button type="button" class="btn btn-outline-primary w-100 mt-3 guest-switch-btn" id="otpReenviar">
        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>Reenviar código
      </button>
      <button type="button" class="btn btn-outline-secondary w-100 mt-2 guest-switch-btn" id="otpVolverDatos">
        <i class="bi bi-pencil" aria-hidden="true"></i>Cambiar celular o correo
      </button>
    </div>`;

  root.innerHTML = wantReg
    ? `
    <div class="guest-auth-wrap">
      <h1 class="h4 mb-1 font-brand">Tus datos</h1>
      <p class="small text-secondary mb-3">Los pedimos para guardarte el pago. En este celular te los recordamos.</p>
      <div class="border bg-white p-3">${guestFormsHtml}</div>
      <div class="mt-3">${supportLinksHtml()}</div>
    </div>`
    : `
    <div class="guest-auth-wrap">
      <h1 class="h4 mb-3 font-brand">Tus datos</h1>
      <div class="border bg-white p-3 mb-3">${guestFormsHtml}</div>
      <div class="mt-3">${supportLinksHtml()}</div>
    </div>`;

  initCiudadSelect(root.querySelector('#guestCiudad'));

  const paneNuevo = root.querySelector('#paneNuevo');
  const paneReturning = root.querySelector('#paneReturning');
  const showReturning = (returning) => {
    paneNuevo?.classList.toggle('d-none', returning);
    paneReturning?.classList.toggle('d-none', !returning);
  };
  root.querySelector('#showReturning')?.addEventListener('click', () =>
    showReturning(true)
  );
  root.querySelector('#showNuevo')?.addEventListener('click', () =>
    showReturning(false)
  );

  const finishAuth = (res, okMsg) => {
    setSession(res.token, res.usuario);
    toast(okMsg, 'success');
    const home = homeHashForUser(res.usuario);
    const rol = String(res.usuario?.rol || '').toLowerCase();
    // Mesa no hereda destino de compra del jugador
    if (rol === 'administrador' || rol === 'operador') {
      location.hash = home;
      return;
    }
    location.hash = consumeAfterLogin(home);
  };

  let otpCorreo = '';
  const otpCtrl = wireOtpInputs(root.querySelector('#formOtpVerificar') || root);

  const showOtpPane = (correo) => {
    otpCorreo = correo;
    root.querySelector('#paneDatos')?.classList.add('d-none');
    root.querySelector('#paneOtp')?.classList.remove('d-none');
    const label = root.querySelector('#otpCorreoLabel');
    if (label) label.textContent = correo;
    otpCtrl.clear();
    otpCtrl.focus();
    toast('Te enviamos un código al correo', 'success');
  };

  const showDatosPane = () => {
    root.querySelector('#paneOtp')?.classList.add('d-none');
    root.querySelector('#paneDatos')?.classList.remove('d-none');
  };

  const handleAuthResponse = (res, btn) => {
    if (res?.requiereOtp) {
      setButtonBusy(btn, false);
      showOtpPane(res.correo || otpCorreo);
      return;
    }
    const rol = String(res?.usuario?.rol || '').toLowerCase();
    finishAuth(
      res,
      rol === 'operador' || rol === 'administrador' ? 'Bienvenido al panel' : 'Listo'
    );
  };

  root.querySelector('#otpVolverDatos')?.addEventListener('click', () => showDatosPane());

  root.querySelector('#otpReenviar')?.addEventListener('click', async () => {
    if (!otpCorreo) return;
    const btn = root.querySelector('#otpReenviar');
    setButtonBusy(btn, true, 'Enviando…');
    try {
      await api('/auth/otp-solicitar', {
        method: 'POST',
        body: {
          organizacionId: requireOrganizacionId(),
          correo: otpCorreo,
        },
      });
      otpCtrl.clear();
      otpCtrl.focus();
      toast('Código reenviado', 'success');
    } catch (e) {
      toast(e.message || 'No se pudo reenviar');
    } finally {
      setButtonBusy(btn, false);
    }
  });

  root.querySelector('#formOtpVerificar')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const codigo = otpCtrl.getCodigo();
    if (!/^[0-9]{6}$/.test(codigo)) {
      toast('Ingresa el código de 6 dígitos', 'warning');
      otpCtrl.focus();
      return;
    }
    const btn = root.querySelector('#otpVerificarBtn');
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
      finishAuth(res, 'Bienvenido al panel');
    } catch (e) {
      toast(e.message || 'Código inválido');
      otpCtrl.clear();
      otpCtrl.focus();
      setButtonBusy(btn, false);
    }
  });

  wireSoloDigitos(root.querySelector('#guestCel'));
  wireSoloDigitos(root.querySelector('#returningCel'));

  root.querySelector('#formGuest')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.target;
    const cel = form.querySelector('[name="celular"]');
    if (cel) cel.value = String(cel.value || '').replace(/\D/g, '').slice(0, 10);
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const btn = root.querySelector('#guestSubmit');
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
      const res = await api('/auth/invitado', {
        method: 'POST',
        body: {
          organizacionId: requireOrganizacionId(),
          ciudadId: Number(fd.get('ciudadId')) || CONFIG.ciudadIdDefault || 1,
          nombre: String(fd.get('nombre')),
          apellido: String(fd.get('apellido')),
          celular,
          correo,
        },
      });
      handleAuthResponse(res, btn);
    } catch (e) {
      toast(e.message || 'No se pudo continuar');
      setButtonBusy(btn, false);
    }
  });

  root.querySelector('#formReturning')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const form = ev.target;
    const cel = form.querySelector('[name="celular"]');
    if (cel) cel.value = String(cel.value || '').replace(/\D/g, '').slice(0, 10);
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    const btn = root.querySelector('#returningSubmit');
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
          celular,
          correo,
        },
      });
      handleAuthResponse(res, btn);
    } catch (e) {
      toast(e.message || 'No se pudo continuar');
      setButtonBusy(btn, false);
    }
  });
}

function wireSoloDigitos(input) {
  if (!input) return;
  input.addEventListener('input', () => {
    input.value = String(input.value || '').replace(/\D/g, '').slice(0, 10);
  });
}
