// API pública de otro sitio propio (clouds.com.bo) — búsqueda de
// disponibilidad de dominios en vivo, llamada directo desde el navegador
// (sin pasar por nuestro backend, ya habilitado para subdominios de
// market.com.bo). Extensiones fijas: cubren lo más pedido sin abrumar con
// una lista enorme en la UI.
const DOMAIN_SEARCH_API = 'https://clouds.com.bo/api/v1/domains/check-availability';
const DOMAIN_EXTENSIONS = [
    'com', 'net', 'org', 'store', 'shop', 'pro', 'club', 'info', 'online',
    'tienda', 'one', 'promo', 'doctor', 'cafe', 'me', 'click', 'tech', 'link',
    'social', 'red', 'stream', 'pizza', 'group',
];

// El costo mayorista en USD por extensión casi no cambia — cachear en
// localStorage evita repetir una consulta lenta (23 extensiones a una API
// externa) en cada visita. La tasa USD->BOB y el margen sí se aplican
// siempre frescos (vienen del servidor en cada carga de página), acá solo
// se cachea el precio en USD.
const CATALOG_CACHE_KEY = 'signup_domain_catalog_v1';
const CATALOG_CACHE_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7 días

function readCatalogCache() {
    try {
        const raw = localStorage.getItem(CATALOG_CACHE_KEY);
        if (!raw) return null;
        const parsed = JSON.parse(raw);
        if (!parsed || !Array.isArray(parsed.items) || !parsed.savedAt) return null;
        if (Date.now() - parsed.savedAt > CATALOG_CACHE_TTL_MS) return null;
        return parsed.items;
    } catch {
        return null;
    }
}

function writeCatalogCache(items) {
    try {
        localStorage.setItem(CATALOG_CACHE_KEY, JSON.stringify({ savedAt: Date.now(), items }));
    } catch {
        // Privado/bloqueado/lleno — sin cache, simplemente vuelve a consultar la próxima vez.
    }
}

function signupWizard(config) {
    return {
        niches: config.niches || [],
        plans: config.plans || {},
        signupEnabled: !!config.signupEnabled,
        domainRegistrationPrice: config.domainRegistrationPrice || 0,
        domainRenewalMarkupPercent: config.domainRenewalMarkupPercent || 0,
        usdToBobRate: config.usdToBobRate || null,

        step: 1,
        handle: '',
        niche: '',
        plan: config.initialPlan && config.plans[config.initialPlan] ? config.initialPlan : 'negocio',

        checking: false,
        available: null,
        statusMessage: '',
        _checkTimer: null,

        // Dominio propio (opcional, solo plan Pro): '' (ninguno),
        // 'register' (nuevo, +Bs domainRegistrationPrice) o 'existing' (ya
        // lo tiene, gratis, solo avisa cuál es).
        domainMode: '',
        domainQuery: '',
        domainSearching: false,
        domainError: '',
        domainResults: [],
        selectedDomain: null,
        existingDomain: '',

        // Catálogo de precios por extensión (independiente de lo que busque
        // el cliente) — se carga una sola vez, con un nombre neutro, para
        // que vea de entrada qué extensiones existen y cuánto cuesta
        // renovar cada una antes de buscar su nombre puntual.
        extensionCatalog: [],
        catalogLoading: false,
        showCatalog: false,

        creating: false,
        createError: '',

        payment: null,
        paymentStatus: 'pending',
        _pollTimer: null,

        adminForm: { name: '', email: '', phone: '', password: '' },
        adminSubmitting: false,
        adminError: '',
        done: null,

        get canContinue() {
            if (!this.signupEnabled || this.available !== true || !this.niche || !this.plan || this.creating) {
                return false;
            }
            if (this.plan === 'pro' && this.domainMode === 'register' && !this.selectedDomain) return false;
            if (this.plan === 'pro' && this.domainMode === 'existing' && !this.existingDomain.trim()) return false;
            return true;
        },

        get totalPrice() {
            const base = (this.plans[this.plan] && this.plans[this.plan].price) || 0;
            const addsFee = this.plan === 'pro' && this.domainMode === 'register' && this.selectedDomain;
            return addsFee ? base + this.domainRegistrationPrice : base;
        },

        selectPlan(id) {
            this.plan = id;
            if (id !== 'pro') {
                this.setDomainMode('');
            }
        },

        setDomainMode(mode) {
            this.domainMode = this.domainMode === mode ? '' : mode;

            if (this.domainMode !== 'register') {
                this.selectedDomain = null;
                this.domainResults = [];
                this.domainError = '';
            }
            if (this.domainMode !== 'existing') {
                this.existingDomain = '';
            }
            if (this.domainMode === 'register') {
                this.loadExtensionCatalog();
            }
        },

        loadExtensionCatalog() {
            if (this.extensionCatalog.length || this.catalogLoading) return;

            const cached = readCatalogCache();
            if (cached) {
                this.extensionCatalog = cached;
                return;
            }

            this.catalogLoading = true;
            fetch(DOMAIN_SEARCH_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                // Nombre neutro solo para consultar precio por extensión —
                // no importa si "está disponible", acá no se muestra
                // disponibilidad, solo el precio de renovación de cada TLD
                // (que es el mismo sin importar el nombre elegido).
                body: JSON.stringify({ domain: 'tunegocio', extensions: DOMAIN_EXTENSIONS, type: 'register' }),
            })
                .then((r) => r.json())
                .then((data) => {
                    this.catalogLoading = false;
                    const results = (data && data.data && data.data.results) || [];
                    // Se cachea el precio mayorista crudo en USD (renewal_price),
                    // no el convertido — el orden ascendente por USD es el mismo
                    // que por Bs (transformación lineal), así que ordenar acá
                    // ya deja el orden correcto para cuando se muestre.
                    const items = results
                        .filter((r) => parseFloat(r.renewal_price))
                        .map((r) => ({ tld: r.tld, renewal_price: r.renewal_price }))
                        .sort((a, b) => parseFloat(a.renewal_price) - parseFloat(b.renewal_price));

                    this.extensionCatalog = items;
                    writeCatalogCache(items);
                })
                .catch(() => {
                    this.catalogLoading = false;
                });
        },

        searchDomains() {
            const query = this.domainQuery.trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
            if (!query) return;

            this.domainSearching = true;
            this.domainError = '';
            this.domainResults = [];
            this.selectedDomain = null;

            fetch(DOMAIN_SEARCH_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ domain: query, extensions: DOMAIN_EXTENSIONS, type: 'register' }),
            })
                .then((r) => r.json())
                .then((data) => {
                    this.domainSearching = false;
                    const results = (data && data.data && data.data.results) || [];
                    if (!results.length) {
                        this.domainError = 'No se pudo buscar dominios. Intenta de nuevo.';
                        return;
                    }
                    this.domainResults = results;
                })
                .catch(() => {
                    this.domainSearching = false;
                    this.domainError = 'No se pudo buscar dominios. Intenta de nuevo.';
                });
        },

        selectDomainOption(domain) {
            this.selectedDomain = this.selectedDomain === domain ? null : domain;
        },

        // Precio de renovación (año 2 en adelante) — el mayorista en USD
        // (renewal_price) más nuestro margen, mostrado en ambas monedas:
        // "USD XX (Bs XX actualmente)". Sin tasa disponible se sigue
        // mostrando el USD (el margen ya aplica), solo se omite el
        // paréntesis en Bs en vez de arriesgar un número inventado.
        renewalPriceLabel(result) {
            const usd = parseFloat(result.renewal_price);
            if (!usd) return null;

            const usdWithMarkup = usd * (1 + this.domainRenewalMarkupPercent / 100);
            const usdLabel = 'USD ' + usdWithMarkup.toFixed(2);

            if (!this.usdToBobRate) return usdLabel;

            const bob = Math.round(usdWithMarkup * this.usdToBobRate);
            return `${usdLabel} (Bs ${bob} actualmente)`;
        },

        onHandleInput() {
            this.handle = this.handle.toLowerCase().replace(/[^a-z0-9-]/g, '');
            this.available = null;
            this.statusMessage = '';
            clearTimeout(this._checkTimer);
            if (!this.handle) return;
            this._checkTimer = setTimeout(() => this.checkHandle(), 550);
        },

        checkHandle() {
            this.checking = true;
            oc.request(null, 'signupWizard::onCheckSubdomain', { data: { handle: this.handle } })
                .then((data) => {
                    this.checking = false;
                    this.available = !!data.available;
                    this.statusMessage = data.message || '';
                })
                .catch(() => {
                    this.checking = false;
                    this.available = false;
                    this.statusMessage = 'No se pudo verificar. Intenta de nuevo.';
                });
        },

        selectNiche(id) {
            this.niche = id;
        },

        goToPayment() {
            if (!this.canContinue) return;
            this.creating = true;
            this.createError = '';
            const domainMode = this.plan === 'pro' ? this.domainMode : '';
            const domainValue = domainMode === 'register'
                ? (this.selectedDomain || '')
                : (domainMode === 'existing' ? this.existingDomain.trim() : '');

            oc.request(null, 'signupWizard::onCreateSignup', {
                data: {
                    handle: this.handle,
                    niche: this.niche,
                    plan: this.plan,
                    domain_mode: domainMode,
                    domain: domainValue,
                },
            })
                .then((data) => {
                    this.creating = false;
                    if (!data.success) {
                        this.createError = data.message || 'No se pudo continuar. Intenta de nuevo.';
                        return;
                    }
                    this.payment = data;
                    this.step = 2;
                    this.startPolling();
                })
                .catch(() => {
                    this.creating = false;
                    this.createError = 'No se pudo continuar. Intenta de nuevo.';
                });
        },

        backToStep1() {
            clearInterval(this._pollTimer);
            this.step = 1;
            this.payment = null;
            this.paymentStatus = 'pending';
        },

        startPolling() {
            clearInterval(this._pollTimer);
            this._pollTimer = setInterval(() => this.checkPaymentStatus(), 5000);
        },

        checkPaymentStatus() {
            if (!this.payment) return;
            oc.request(null, 'signupWizard::onCheckPaymentStatus', {
                data: { tenant_id: this.payment.tenant_id, reference: this.payment.reference },
            }).then((data) => {
                if (data.status === 'paid') {
                    clearInterval(this._pollTimer);
                    this.paymentStatus = 'paid';
                }
            });
        },

        submitAdmin() {
            this.adminSubmitting = true;
            this.adminError = '';
            oc.request(null, 'signupWizard::onCreateAdmin', {
                data: {
                    tenant_id: this.payment.tenant_id,
                    reference: this.payment.reference,
                    name: this.adminForm.name,
                    email: this.adminForm.email,
                    phone: this.adminForm.phone,
                    password: this.adminForm.password,
                },
            })
                .then((data) => {
                    this.adminSubmitting = false;
                    if (!data.success) {
                        this.adminError = data.message || 'No se pudo crear tu cuenta.';
                        return;
                    }
                    this.done = data;
                })
                .catch(() => {
                    this.adminSubmitting = false;
                    this.adminError = 'No se pudo crear tu cuenta. Intenta de nuevo.';
                });
        },
    };
}
