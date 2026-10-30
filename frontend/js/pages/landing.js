import { api } from '../api.js';
import { isLoggedIn } from '../auth.js';
import { CONFIG } from '../config.js';
import { getOrganizacionId, setOrganizacionId } from '../org-context.js';
import {
  formatFecha,
  formatMoney,
  estadoBadge,
  escapeHtml,
  toast,
  loadingHtml,
  modalidadKey,
  premioLineHtml,
  premiosListHtml,
} from '../ui.js';

const MODALIDAD_BLURB = {
  linea: 'Forma la L en tu cartón',
  equis: 'Cruza las dos diagonales y gana',
  completa: 'Llena todo el cartón — el premio gordo',
};

function blurbModalidad(m) {
  return MODALIDAD_BLURB[modalidadKey(m)] || 'Cierra el patrón en tu cartón';
}

function waUrl(text) {
  if (!CONFIG.support?.whatsapp) return '#';
  return (
    'https://wa.me/' +
    CONFIG.support.whatsapp +
    '?text=' +
    encodeURIComponent(text || 'Hola, quiero comprar un cartón del bingo.')
  );
}

function partidasActivas(partidas) {
  const list = (partidas || []).filter((p) =>
    ['pendiente', 'en_curso'].includes(p.estado)
  );
  list.sort((a, b) => {
    if (a.estado === 'en_curso' && b.estado !== 'en_curso') return -1;
    if (b.estado === 'en_curso' && a.estado !== 'en_curso') return 1;
    return String(a.fecha || '').localeCompare(String(b.fecha || ''));
  });
  return list;
}

function goComprar(partida) {
  if (!partida?.id) {
    location.hash = '#/landing';
    return;
  }
  // Primero cartones; datos solo al pagar.
  location.hash = `#/comprar/${partida.id}`;
}

function ctaLabel() {
  return 'Quiero jugar';
}

function guestHintHtml() {
  if (isLoggedIn()) return '';
  return '';
}

function heroEmptyHtml() {
  return `
    <section class="landing__hero">
      <div class="landing__hero-inner">
        <p class="landing__kicker mb-0">Pronto en vivo</p>
        <h1 class="landing__title font-brand">La mesa se está preparando</h1>
        <p class="landing__lead mb-0">Cuando abra la partida, aquí eliges cartón y entras a jugar.</p>
      </div>
    </section>`;
}

function heroStatusBadge(partida) {
  const estado = String(partida.estado || '');
  const cuando = escapeHtml(formatFecha(partida.fecha));
  if (estado === 'en_curso') {
    return `<span class="badge bg-light text-success border border-success">
      <i class="bi bi-broadcast me-1" aria-hidden="true"></i>${cuando} · En vivo ahora
    </span>`;
  }
  if (estado === 'pendiente') {
    return `<span class="badge bg-light text-primary border border-primary">
      <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>${cuando} · Próximo a jugar
    </span>`;
  }
  return estadoBadge(estado);
}

function heroPartidaHtml(partida) {
  return `
    <section class="landing__hero">
      <div class="landing__hero-inner">
        <div class="landing__hero-top">
          ${heroStatusBadge(partida)}
        </div>
        <h1 class="landing__title font-brand">${escapeHtml(partida.nombre)}</h1>
        <p class="landing__lead">Elige tu cartón y prepárate: la mesa te espera.</p>
        <div class="landing__hero-row">
          <div class="landing__meta">
            <div class="landing__price-block">
              <span class="landing__price">${formatMoney(partida.precioCarton)}</span>
              <span class="landing__price-note">por cartón</span>
            </div>
          </div>
          <button type="button" class="btn btn-warning fw-semibold landing__cta" data-landing-buy="${escapeHtml(String(partida.id))}">
            <i class="bi bi-lightning-charge-fill me-1" aria-hidden="true"></i>${ctaLabel()}
          </button>
        </div>
      </div>
    </section>`;
}

/**
 * Mini cartón 5×5 que ilustra el patrón ganador (línea / equis / completa).
 * Solo visual — no son números de venta.
 */
function patternBoardHtml(modalidad) {
  const key = String(modalidad || '').toLowerCase();
  const cells = [];
  for (let r = 0; r < 5; r++) {
    for (let c = 0; c < 5; c++) {
      let win = false;
      if (key === 'linea' || key === 'l' || key === 'ele') {
        // Línea = patrón en L (columna B + fila inferior)
        win = c === 0 || r === 4;
      } else if (key === 'equis') {
        win = r === c || r + c === 4;
      } else if (key === 'completa') {
        win = true;
      } else {
        win = c === 0 || r === 4;
      }
      const free = r === 2 && c === 2 && key !== 'completa';
      cells.push(
        `<span class="premio-board__cell${win ? ' is-win' : ''}${free ? ' is-free' : ''}"></span>`
      );
    }
  }
  return `
    <div class="premio-board" aria-hidden="true">
      <div class="premio-board__heads">
        <span>B</span><span>I</span><span>N</span><span>G</span><span>O</span>
      </div>
      <div class="premio-board__grid">${cells.join('')}</div>
    </div>`;
}

function premioCardHtml(m) {
  const blurb = blurbModalidad(m.modalidad);

  return `
    <article class="premio-card h-100">
      <div class="premio-card__visual">
        ${patternBoardHtml(m.modalidad)}
        <span class="premio-card__glow" aria-hidden="true"></span>
      </div>
      <div class="premio-card__body">
        <p class="premio-card__hook mb-1">Te llevas</p>
        <h3 class="premio-card__title premio-card__amount font-brand mb-2">
          ${premioLineHtml(m.modalidad, m.premio, 'inline')}
        </h3>
        <p class="premio-card__blurb mb-0">${escapeHtml(blurb)}</p>
      </div>
    </article>`;
}

/** Premios ilustrados + CTA. */
function ofertaHtml(partida) {
  const list = Array.isArray(partida.modalidades) ? partida.modalidades : [];
  const premiosBlock = list.length
    ? `<div class="row g-3">${list
        .map(
          (m) => `
        <div class="col-12 col-sm-4">
          ${premioCardHtml(m)}
        </div>`
        )
        .join('')}</div>`
    : `<p class="small text-secondary mb-0">Premios por confirmar</p>`;

  return `
    <section class="landing-band landing-band--white" aria-labelledby="landingOfertaTitle">
      <div class="landing-band__inner">
        <header class="landing-band__head">
          <div>
            <p class="landing-band__eyebrow mb-1">🤑 Lo que puedes ganar</p>
            <h2 id="landingOfertaTitle" class="h4 font-brand mb-0">Premios de la partida 🎉</h2>
          </div>
        </header>
        <div class="landing-oferta">
          ${premiosBlock}
          <button type="button" class="btn btn-success w-100 mt-4" data-landing-buy="${escapeHtml(String(partida.id))}">
            <i class="bi bi-cart3 me-1" aria-hidden="true"></i>${ctaLabel()}
          </button>
          ${guestHintHtml()}
        </div>
      </div>
    </section>`;
}

function cardPartidaHtml(p) {
  return `
    <article class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div class="min-w-0">
            <h2 class="h5 card-title font-brand mb-1">${escapeHtml(p.nombre)}</h2>
            <p class="small text-secondary mb-0">
              <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
              ${escapeHtml(formatFecha(p.fecha))}
            </p>
          </div>
          ${estadoBadge(p.estado)}
        </div>
        <div class="d-flex align-items-baseline gap-2 my-3">
          <span class="fs-3 fw-bold text-primary lh-1 font-brand">${formatMoney(p.precioCarton)}</span>
          <span class="text-secondary small">por cartón</span>
        </div>
        <p class="small text-secondary text-uppercase fw-semibold mb-1 letter-spacing">Premios</p>
        ${premiosListHtml(p.modalidades, {
          ulClass: 'list-group list-group-flush',
          liClass: 'list-group-item px-0',
        })}
      </div>
      <div class="card-footer bg-white border-top-0 pt-0 pb-3">
        <button type="button" class="btn btn-success w-100" data-landing-buy="${escapeHtml(String(p.id))}">
          <i class="bi bi-cart3 me-1" aria-hidden="true"></i>${ctaLabel()}
        </button>
        ${guestHintHtml()}
      </div>
    </article>`;
}

function starsHtml(rating) {
  const n = Math.max(0, Math.min(5, Math.round(Number(rating) || 0)));
  let html = '<span class="landing-stars text-warning" aria-label="' + n + ' de 5">';
  for (let i = 1; i <= 5; i++) {
    html += `<i class="bi bi-star${i <= n ? '-fill' : ''}" aria-hidden="true"></i>`;
  }
  html += '</span>';
  return html;
}

function reviewSlideHtml(t) {
  const initial = escapeHtml(
    String(t.name || '?')
      .trim()
      .charAt(0)
      .toUpperCase() || '?'
  );
  return `
    <figure class="card shadow-sm border-0 landing-review mx-auto">
      <div class="card-body d-flex flex-column">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="landing-review__avatar" aria-hidden="true">${initial}</span>
          <div class="min-w-0">
            <figcaption class="fw-semibold mb-0 text-truncate">${escapeHtml(t.name || 'Cliente')}</figcaption>
            ${
              t.city
                ? `<p class="small text-secondary mb-0 text-truncate">${escapeHtml(t.city)}</p>`
                : ''
            }
          </div>
        </div>
        ${starsHtml(t.rating)}
        <blockquote class="mb-0 mt-2 flex-grow-1">“${escapeHtml(t.text || '')}”</blockquote>
      </div>
    </figure>`;
}

function testimoniosHtml() {
  const items = Array.isArray(CONFIG.testimonials) ? CONFIG.testimonials : [];
  if (!items.length) return '';

  const avg =
    items.reduce((sum, t) => sum + (Number(t.rating) || 0), 0) / items.length;
  const avgLabel = avg.toLocaleString('es-CO', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });
  const multi = items.length > 1;

  return `
    <section class="landing-band landing-band--tint" aria-labelledby="landingReviewsTitle">
      <div class="landing-band__inner">
      <header class="landing-band__head">
        <div>
          <p class="landing-band__eyebrow mb-1">Opiniones</p>
          <h2 id="landingReviewsTitle" class="h4 font-brand mb-1">Clientes satisfechos</h2>
          <p class="small text-secondary mb-0">Desliza para leer más reseñas.</p>
        </div>
        <div class="landing-reviews-summary text-end">
          ${starsHtml(avg)}
          <div class="small text-secondary">${avgLabel} · ${items.length} reseñas</div>
        </div>
      </header>

      <div
        id="landingReviewsCarousel"
        class="carousel slide landing-reviews-carousel"
        ${multi ? 'data-bs-ride="carousel"' : ''}
        data-bs-interval="5500"
        data-bs-touch="true"
        data-bs-wrap="true"
      >
        ${
          multi
            ? `<div class="carousel-indicators landing-reviews-indicators">
                ${items
                  .map(
                    (_, i) => `
                  <button type="button" data-bs-target="#landingReviewsCarousel" data-bs-slide-to="${i}"
                    class="${i === 0 ? 'active' : ''}" aria-current="${i === 0 ? 'true' : 'false'}"
                    aria-label="Reseña ${i + 1}"></button>`
                  )
                  .join('')}
              </div>`
            : ''
        }
        <div class="carousel-inner">
          ${items
            .map(
              (t, i) => `
            <div class="carousel-item ${i === 0 ? 'active' : ''}">
              ${reviewSlideHtml(t)}
            </div>`
            )
            .join('')}
        </div>
        ${
          multi
            ? `
        <button class="carousel-control-prev landing-reviews-ctrl" type="button" data-bs-target="#landingReviewsCarousel" data-bs-slide="prev">
          <span class="landing-reviews-ctrl__btn" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
          <span class="visually-hidden">Anterior</span>
        </button>
        <button class="carousel-control-next landing-reviews-ctrl" type="button" data-bs-target="#landingReviewsCarousel" data-bs-slide="next">
          <span class="landing-reviews-ctrl__btn" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
          <span class="visually-hidden">Siguiente</span>
        </button>`
            : ''
        }
      </div>
      </div>
    </section>`;
}

function confianzaHtml() {
  return `
    <section class="landing-band landing-band--white" aria-label="Confianza">
      <div class="landing-band__inner">
        <div class="row g-2 text-center landing-trust-row">
          <div class="col-4">
            <i class="bi bi-shield-check text-primary d-block mb-1" aria-hidden="true"></i>
            <span class="small fw-semibold">PSE / transferencia</span>
          </div>
          <div class="col-4">
            <i class="bi bi-phone text-primary d-block mb-1" aria-hidden="true"></i>
            <span class="small fw-semibold">Juega en el celular</span>
          </div>
          <div class="col-4">
            <i class="bi bi-whatsapp text-success d-block mb-1" aria-hidden="true"></i>
            <span class="small fw-semibold">Soporte WhatsApp</span>
          </div>
        </div>
      </div>
    </section>`;
}

function cierreHtml(partida) {
  if (!partida?.id) return '';
  return `
    <section class="landing-band landing-band--tint" aria-label="Comprar">
      <div class="landing-band__inner text-center">
        <h2 class="h5 font-brand mb-2">¿Listo para jugar?</h2>
        <p class="small text-secondary mb-3">
          ${escapeHtml(partida.nombre)} · ${formatMoney(partida.precioCarton)} / cartón
        </p>
        <button type="button" class="btn btn-success px-4" data-landing-buy="${escapeHtml(String(partida.id))}">
          <i class="bi bi-cart3 me-1" aria-hidden="true"></i>${ctaLabel()}
        </button>
        ${guestHintHtml()}
      </div>
    </section>`;
}

function wireLanding(root, activas, featured) {
  root.querySelectorAll('[data-landing-buy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = Number(btn.getAttribute('data-landing-buy'));
      const partida = activas.find((p) => Number(p.id) === id) || featured;
      goComprar(partida);
    });
  });

  const carouselEl = root.querySelector('#landingReviewsCarousel');
  if (carouselEl && window.bootstrap?.Carousel) {
    window.bootstrap.Carousel.getOrCreateInstance(carouselEl, {
      interval: 5500,
      ride: 'carousel',
      touch: true,
      wrap: true,
      pause: 'hover',
    });
  }
}

function pasosHtml() {
  const pasos = [
    { n: '1', title: 'Elige cartones', text: 'Mira los disponibles y agrégalos al carrito.' },
    { n: '2', title: 'Paga', text: 'Al pagar dejas tus datos y eliges PSE o transferencia.' },
    { n: '3', title: 'Juega', text: 'Entra en vivo cuando inicie la partida.' },
  ];

  return `
    <section class="landing-band landing-band--muted" aria-labelledby="landingHowTitle">
      <div class="landing-band__inner">
        <header class="landing-band__head">
          <div>
            <p class="landing-band__eyebrow mb-1">Así de simple</p>
            <h2 id="landingHowTitle" class="h4 font-brand mb-0">Cómo funciona</h2>
          </div>
        </header>
        <ol class="list-group list-group-numbered landing-steps-list">
          ${pasos
            .map(
              (paso) => `
            <li class="list-group-item d-flex justify-content-between align-items-start">
              <div class="ms-2 me-auto">
                <div class="fw-semibold font-brand">${paso.title}</div>
                <span class="small text-secondary">${paso.text}</span>
              </div>
            </li>`
            )
            .join('')}
        </ol>
      </div>
    </section>`;
}

function faqHtml(wa) {
  const hours = escapeHtml(CONFIG.support?.hours || 'Lun–Sáb 8:00–20:00');
  return `
    <section class="landing-band landing-band--white" aria-labelledby="landingFaqTitle">
      <div class="landing-band__inner">
      <header class="landing-band__head">
        <div>
          <p class="landing-band__eyebrow mb-1">Ayuda</p>
          <h2 id="landingFaqTitle" class="h4 font-brand mb-0">Preguntas frecuentes</h2>
        </div>
      </header>
      <div class="accordion landing-faq" id="landingFaq">
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="false" aria-controls="faq1">
              ¿Cómo compro un cartón?
            </button>
          </h3>
          <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
            <div class="accordion-body small text-secondary">
              Toca <strong>Quiero jugar</strong>, elige cartones y al pagar deja tus datos (PSE o transferencia con comprobante).
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
              ¿Cuándo queda confirmada la compra?
            </button>
          </h3>
          <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
            <div class="accordion-body small text-secondary">
              Con PSE, al completar el pago en OpenPay. Con transferencia, el cartón queda en revisión hasta que se valide el comprobante.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
              ¿Cómo sigo la partida en vivo?
            </button>
          </h3>
          <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
            <div class="accordion-body small text-secondary">
              Cuando <strong>ya pagaste tu cartón</strong> y el operador inicia la partida, entra a <strong>Jugar</strong> y toca <strong>Jugar en vivo</strong>. Tú marcas los números en tu cartón.
            </div>
          </div>
        </div>
        <div class="accordion-item">
          <h3 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
              ¿Qué hago si tengo un problema?
            </button>
          </h3>
          <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#landingFaq">
            <div class="accordion-body small text-secondary">
              Usa el centro de ayuda o escríbenos por WhatsApp. Soporte: ${hours}.
            </div>
          </div>
        </div>
      </div>
      <div class="d-grid gap-2 d-sm-flex mt-3">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ayudaModal">
          <i class="bi bi-life-preserver me-1" aria-hidden="true"></i>Centro de ayuda
        </button>
        <a class="btn btn-outline-success" href="${wa}" target="_blank" rel="noopener noreferrer">
          <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>WhatsApp
        </a>
      </div>
      </div>
    </section>`;
}

function setLandingBleed(on) {
  const mainEl = document.getElementById('appMain');
  if (!mainEl) return;
  mainEl.classList.toggle('app-main--bleed', on);
  mainEl.classList.toggle('container-lg', !on);
  mainEl.classList.toggle('py-3', !on);
  mainEl.classList.toggle('py-md-4', !on);
}

export async function renderLanding(root) {
  setLandingBleed(true);
  root.innerHTML = loadingHtml('Cargando…');

  const orgId = getOrganizacionId();
  let activas = [];

  try {
    const partidas = await api(
      '/partidas/publicas' +
        (orgId ? '?organizacionId=' + encodeURIComponent(orgId) : '')
    );
    activas = partidasActivas(partidas);
    const primeraOrg = activas[0]?.organizacionId;
    if (primeraOrg) setOrganizacionId(primeraOrg);
  } catch (e) {
    toast(e.message || 'No se pudo cargar el bingo');
  }

  const wa = escapeHtml(waUrl());
  const featured = activas[0] || null;

  if (!featured) {
    root.innerHTML = `
      <div class="landing">
        ${heroEmptyHtml()}
        <section class="landing-band landing-band--white">
          <div class="landing-band__inner">
            <div class="alert alert-light border mb-3" role="status">
              Cuando abramos un bingo, aquí verás fecha, precio y premios.
            </div>
            <div class="d-grid gap-2">
              <a class="btn btn-success btn-lg" href="${wa}" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Avisarme por WhatsApp
              </a>
            </div>
          </div>
        </section>
        ${pasosHtml()}
        ${confianzaHtml()}
        ${testimoniosHtml()}
        ${faqHtml(wa)}
      </div>`;
    wireLanding(root, [], null);
    return;
  }

  // Misma organización (contexto de la partida / sesión): destacado + resto.
  const otras = activas.slice(1);
  const otrasHtml = otras.length
    ? `
      <section class="landing-band landing-band--muted" aria-labelledby="landingOtrasTitle">
        <div class="landing-band__inner">
          <header class="landing-band__head">
            <div>
              <p class="landing-band__eyebrow mb-1">📅 Próximas fechas</p>
              <h2 id="landingOtrasTitle" class="h4 font-brand mb-0">Otros bingos para jugar</h2>
              <p class="small text-secondary mb-0 mt-1">Elige uno y toca <strong>Quiero jugar</strong>.</p>
            </div>
          </header>
          <div class="row g-3">
            ${otras
              .map(
                (p) => `
              <div class="col-12 col-md-6">
                ${cardPartidaHtml(p)}
              </div>`
              )
              .join('')}
          </div>
        </div>
      </section>`
    : '';

  // Embudo: hero → premios → más bingos de la org → cómo → confianza → FAQ → cierre
  root.innerHTML = `
    <div class="landing">
      ${heroPartidaHtml(featured)}
      ${ofertaHtml(featured)}
      ${otrasHtml}
      ${pasosHtml()}
      ${confianzaHtml()}
      ${testimoniosHtml()}
      ${faqHtml(wa)}
      ${cierreHtml(featured)}
    </div>`;

  wireLanding(root, activas, featured);
}
