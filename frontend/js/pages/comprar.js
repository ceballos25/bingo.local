import { api } from '../api.js';
import { setOrganizacionId } from '../org-context.js';
import {
  escapeHtml,
  formatMoney,
  renderCartonHtml,
  toast,
  loadingHtml,
  emptyHtml,
} from '../ui.js';
import {
  getCartIds,
  addCartItem,
  replaceCartIds,
  setCartContext,
  updateCartBadge,
  flyToCart,
  openCartPanel,
} from '../cart.js';

const PER_PAGE = 24;

function pagerHtml(pag) {
  const page = Number(pag.page) || 1;
  const totalPages = Number(pag.totalPages) || 1;
  const total = Number(pag.total) || 0;
  if (totalPages <= 1) {
    return `<span class="badge text-bg-primary">${total} disponibles</span>`;
  }
  const prevDisabled = page <= 1 ? 'disabled' : '';
  const nextDisabled = page >= totalPages ? 'disabled' : '';
  return `
    <div class="comprar-pager">
      <span class="badge text-bg-primary">${total} disponibles</span>
      <div class="btn-group" role="group" aria-label="Páginas de cartones">
        <button type="button" class="btn btn-sm btn-outline-primary" data-page="${page - 1}" ${prevDisabled} aria-label="Anterior">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
        <span class="btn btn-sm btn-outline-primary disabled">
          ${page} / ${totalPages}
        </span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-page="${page + 1}" ${nextDisabled} aria-label="Siguiente">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
      </div>
    </div>`;
}

function gridHtml(disponibles, precio, inCartSet, gratis) {
  return `
    <div class="row g-3" id="comprarGrid">
      ${disponibles
        .map((c) => {
          const inCart = inCartSet.has(Number(c.id));
          return `
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="card h-100 carton-pick ${inCart ? 'is-in-cart' : ''}" data-id="${c.id}" aria-pressed="${inCart ? 'true' : 'false'}">
            <div class="card-body p-2 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">Cartón #${escapeHtml(String(c.id))}</span>
                <span class="small text-secondary">${gratis ? 'Gratis' : formatMoney(precio)}</span>
              </div>
              ${renderCartonHtml(c.numeros)}
              <button type="button" class="btn-sm mt-2 w-100 btn-add-cart ${inCart ? 'btn btn-outline-success' : 'btn btn-success'}" data-id="${c.id}" ${inCart ? 'disabled' : ''}>
                ${
                  inCart
                    ? '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Agregado'
                    : '<i class="bi bi-cart-plus me-1" aria-hidden="true"></i>Agregar'
                }
              </button>
            </div>
          </div>
        </div>`;
        })
        .join('')}
    </div>`;
}

export async function renderComprar(root, partidaId) {
  root.innerHTML = loadingHtml('Cargando cartones…');

  let page = 1;
  let partida = null;
  let cartMeta = null;
  let porId = new Map();

  const syncCards = (ids) => {
    const set = new Set(ids);
    root.querySelectorAll('.carton-pick').forEach((card) => {
      const id = Number(card.getAttribute('data-id'));
      const inCart = set.has(id);
      const btn = card.querySelector('.btn-add-cart');
      card.classList.toggle('is-in-cart', inCart);
      card.setAttribute('aria-pressed', inCart ? 'true' : 'false');
      if (!btn) return;
      if (inCart) {
        btn.className = 'btn-sm mt-2 w-100 btn-add-cart btn btn-outline-success';
        btn.innerHTML = '<i class="bi bi-check2 me-1" aria-hidden="true"></i>Agregado';
        btn.disabled = true;
      } else {
        btn.className = 'btn-sm mt-2 w-100 btn-add-cart btn btn-success';
        btn.innerHTML = '<i class="bi bi-cart-plus me-1" aria-hidden="true"></i>Agregar';
        btn.disabled = false;
      }
    });
    updateCartBadge();
  };

  const wireGrid = () => {
    root.querySelector('#btnVerCarrito')?.addEventListener('click', () => {
      openCartPanel();
    });

    root.querySelectorAll('[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const next = Number(btn.getAttribute('data-page'));
        if (!next || btn.disabled) return;
        page = next;
        loadPage(false).catch((e) => toast(e.message || 'Error'));
      });
    });

    root.querySelectorAll('.btn-add-cart').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = Number(btn.getAttribute('data-id'));
        if (!id || !porId.has(id) || btn.disabled || btn.getAttribute('aria-busy') === 'true') {
          return;
        }
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        addCartItem(partidaId, id, cartMeta);
        flyToCart(btn);
        syncCards(getCartIds(partidaId));
      });
    });
  };

  const loadPage = async (first) => {
    if (!first) {
      const grid = root.querySelector('#comprarGridWrap');
      if (grid) {
        grid.innerHTML = loadingHtml('Cargando…');
      }
    }

    const vitrina = await api(
      '/partidas/vitrina?id=' +
        encodeURIComponent(partidaId) +
        '&page=' +
        encodeURIComponent(page) +
        '&perPage=' +
        encodeURIComponent(PER_PAGE)
    );

    partida = vitrina?.partida;
    const disponibles = Array.isArray(vitrina?.cartones) ? vitrina.cartones : [];
    const pag = vitrina?.paginacion || {
      page: 1,
      perPage: PER_PAGE,
      total: disponibles.length,
      totalPages: 1,
    };

    if (!partida) {
      root.innerHTML = emptyHtml(
        'No encontramos esa partida.',
        '<a class="btn btn-success" href="#/landing">Ver bingos</a>'
      );
      return;
    }

    disponibles.forEach((c) => porId.set(Number(c.id), c));

    const precio = Number(partida.precioCarton) || 0;
    const gratis = !!partida.gratis || precio <= 0;
    if (partida.organizacionId) {
      setOrganizacionId(partida.organizacionId);
    }

    const modalidades = Array.isArray(partida.modalidades) ? partida.modalidades : [];
    cartMeta = {
      precio,
      nombre: partida.nombre || '',
      modalidades,
      gratis,
      onChange: syncCards,
    };

    // Conservar carrito entre páginas (no filtrar por la página actual).
    if (first) {
      replaceCartIds(partidaId, getCartIds(partidaId), cartMeta);
    }
    setCartContext({
      partidaId: Number(partidaId),
      ...cartMeta,
    });

    const cartIds = getCartIds(partidaId);
    const inCartSet = new Set(cartIds);

    if (first) {
      let html = `
        <nav aria-label="breadcrumb" class="mb-2">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="#/landing">Bingos</a></li>
            <li class="breadcrumb-item active" aria-current="page">Elegir cartones</li>
          </ol>
        </nav>
        <div class="comprar-head">
          <div class="min-w-0">
            <h1 class="h4 font-brand mb-1">${escapeHtml(partida.nombre)}</h1>
            <p class="text-secondary small mb-0">
              Elige tus cartones · los datos los pedimos al pagar
            </p>
          </div>
          <div class="text-end flex-shrink-0">
            <div class="fs-5 fw-bold text-primary">${gratis ? 'Gratis' : formatMoney(precio)}</div>
            <div class="text-secondary small">${gratis ? 'sin costo' : 'por cartón'}</div>
          </div>
        </div>`;

      if (Number(pag.total) === 0) {
        html += emptyHtml('No hay cartones disponibles en esta partida.');
        root.innerHTML = html;
        return;
      }

      html += `
        <div class="comprar-toolbar mb-3">
          ${pagerHtml(pag)}
          <button type="button" class="btn btn-sm btn-outline-success" id="btnVerCarrito">
            <i class="bi bi-cart3 me-1" aria-hidden="true"></i>Ver carrito
            <span class="nav-cart-badge badge text-bg-danger rounded-pill ms-1" id="comprarCartBadge" hidden>0</span>
          </button>
        </div>
        <div id="comprarGridWrap">${gridHtml(disponibles, precio, inCartSet, gratis)}</div>
        <div class="comprar-toolbar comprar-toolbar--bottom mt-3">
          ${pagerHtml(pag)}
        </div>`;

      root.innerHTML = html;
      wireGrid();
      syncCards(cartIds);
      return;
    }

    const wrap = root.querySelector('#comprarGridWrap');
    const toolbars = root.querySelectorAll('.comprar-toolbar');
    if (wrap) {
      wrap.innerHTML = gridHtml(disponibles, precio, inCartSet, gratis);
    }
    toolbars.forEach((bar, i) => {
      const cartBtn =
        i === 0
          ? `<button type="button" class="btn btn-sm btn-outline-success" id="btnVerCarrito">
              <i class="bi bi-cart3 me-1" aria-hidden="true"></i>Ver carrito
            </button>`
          : '';
      bar.innerHTML = `${pagerHtml(pag)}${cartBtn}`;
    });
    wireGrid();
    syncCards(getCartIds(partidaId));
    root.querySelector('#comprarGridWrap')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  try {
    await loadPage(true);
  } catch (e) {
    toast(e.message || 'Error al cargar');
    root.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${escapeHtml(e.message)}</div>`;
  }
}
