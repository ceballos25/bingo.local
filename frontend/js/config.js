/** Config PWA jugador — portable a otro hosting. */
export const CONFIG = {
  /** Base de la API (mismo origen si sirves /app desde bingo.local). */
  apiBase: '',

  /**
   * La organización ya no vive aquí: se toma de la partida
   * (`#/comprar/{id}` → vitrina) o del JWT en mesa.
   * Ver `org-context.js`.
   */

  /** Ciudad por defecto para acceso invitado (sin selector). */
  ciudadIdDefault: 1,

  /** Versión de la app (footer / soporte). */
  appVersion: '1.0',

  /** Marca visible (nav, título, footer). */
  brandName: 'CCM Software',
  brandTagline: 'Bingo en vivo',
  logoPath: './icons/logo.png',

  /**
   * Soporte / confianza (cámbialos por los reales del cliente).
   * whatsapp: código país + número, sin + ni espacios (ej. 573001112233).
   */
  support: {
    whatsapp: '573001112233',
    email: 'contacto@ccmsoftware.com.co',
    phoneLabel: '300 111 2233',
    hours: 'Lun–Sáb 8:00–20:00',
  },

  /**
   * Fallback si la API de cuentas-pago no responde.
   * Fuente de verdad: GET /cuentas-pago/publicas (admin las edita en BD).
   */
  transferencia: {
    cuentas: [
      {
        id: 'nequi',
        mark: 'N',
        markClass: 'text-bg-dark',
        bank: 'Nequi',
        label: '324 589 4268',
        copy: '3245894268',
        holder: 'Cristian Ceballos',
      },
      {
        id: 'bancolombia',
        mark: 'B',
        markClass: 'text-bg-warning',
        bank: 'Bancolombia · Ahorros',
        label: '611 2711 9979',
        copy: '61127119979',
        holder: 'Cristian Ceballos',
      },
      {
        id: 'breb',
        mark: '@',
        markClass: 'text-bg-primary',
        bank: 'Llave Bre-B',
        label: '@CEBALLOS1003',
        copy: '@CEBALLOS1003',
        holder: 'Cristian Ceballos',
      },
    ],
  },

  /** URL de retorno PSE (página en esta PWA). */
  pseRedirectPath: '/app/pago-ok.html',

  /**
   * Giro del rombo / bombo (admin mesa y jugadores usan los mismos valores).
   * giroMs: cuánto dura el giro. periodoSeg: segundos por vuelta (más bajo = más rápido).
   * holdMs: cuánto queda la balota grande en pantalla.
   * variar: si true, cada número varía un poco (misma fórmula en todas las pantallas).
   */
  sorteo: {
    giroMs: 2800,
    periodoSeg: 0.45,
    holdMs: 1400,
    variar: true,
  },

  /**
   * Testimonios de la landing (cámbialos por reseñas reales de tus jugadores).
   * rating: 1–5. Si el array está vacío, la sección no se muestra.
   */
  testimonials: [
    {
      name: 'Carolina R.',
      city: 'Bogotá',
      rating: 5,
      text: 'Compré el cartón por PSE en minutos y el bingo en vivo se entendió fácil. El soporte por WhatsApp respondió rápido.',
    },
    {
      name: 'Andrés P.',
      city: 'Medellín',
      rating: 5,
      text: 'Me gustó poder ver el precio y los premios antes de pagar. Los cartones se ven claros y el carrito no se complica.',
    },
    {
      name: 'Diana L.',
      city: 'Cali',
      rating: 5,
      text: 'Transferí por Nequi, subí el comprobante y me avisaron cuando quedó listo. Jugar desde el celular se siente cómodo.',
    },
    {
      name: 'Julián S.',
      city: 'Barranquilla',
      rating: 5,
      text: 'Entré desde el anuncio, creé la cuenta y enseguida estaba eligiendo cartón. Todo se siente ordenado.',
    },
    {
      name: 'Mariana G.',
      city: 'Bucaramanga',
      rating: 5,
      text: 'La primera vez me asustaba el pago, pero con PSE fue directo. Luego marqué mis números sin enredos.',
    },
    {
      name: 'Felipe H.',
      city: 'Pereira',
      rating: 5,
      text: 'Compré dos cartones y el carrito sumó bien el total. La app se siente seria, no improvisada.',
    },
    {
      name: 'Natalia V.',
      city: 'Manizales',
      rating: 5,
      text: 'Me ayudaron por WhatsApp cuando no encontraba la partida. En menos de cinco minutos ya tenía cuenta y cartón.',
    },
    {
      name: 'Ricardo M.',
      city: 'Cartagena',
      rating: 5,
      text: 'Jugué en vivo desde el celular y las balotas se veían claras. Volveré a comprar en el próximo bingo.',
    },
    {
      name: 'Paola C.',
      city: 'Ibagué',
      rating: 5,
      text: 'Subí el comprobante de Bre-B y quedó en revisión rapidito. Me llegó el aviso cuando confirmó.',
    },
    {
      name: 'Esteban Q.',
      city: 'Neiva',
      rating: 5,
      text: 'Todo el flujo es simple: eliges cartón, pagas y esperas la hora. Ideal si no eres experto en apps.',
    },
  ],
};
