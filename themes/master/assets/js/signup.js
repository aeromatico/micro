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

        // Dominio propio (opcional, solo plan Pro)
        wantsDomain: false,
        domainQuery: '',
        domainSearching: false,
        domainError: '',
        domainResults: [],
        selectedDomain: null,

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
            return this.signupEnabled && this.available === true && !!this.niche && !!this.plan && !this.creating;
        },

        get totalPrice() {
            const base = (this.plans[this.plan] && this.plans[this.plan].price) || 0;
            return this.selectedDomain ? base + this.domainRegistrationPrice : base;
        },

        selectPlan(id) {
            this.plan = id;
            if (id !== 'pro') {
                this.wantsDomain = false;
                this.selectedDomain = null;
                this.domainResults = [];
            }
        },

        toggleWantsDomain() {
            this.wantsDomain = !this.wantsDomain;
            if (!this.wantsDomain) {
                this.selectedDomain = null;
                this.domainResults = [];
                this.domainError = '';
            }
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

        // Precio de renovación (año 2 en adelante) en Bs, para mostrar junto
        // a cada resultado — el mayorista en USD (renewal_price) más nuestro
        // margen, convertido con la tasa que ya trae el servidor. Null (no
        // se muestra nada) si la tasa no está disponible, en vez de arriesgar
        // un número inventado.
        renewalPriceBob(result) {
            if (!this.usdToBobRate) return null;
            const usd = parseFloat(result.renewal_price);
            if (!usd) return null;
            const withMarkup = usd * (1 + this.domainRenewalMarkupPercent / 100);
            return Math.round(withMarkup * this.usdToBobRate);
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
            oc.request(null, 'signupWizard::onCreateSignup', {
                data: {
                    handle: this.handle,
                    niche: this.niche,
                    plan: this.plan,
                    domain: this.plan === 'pro' ? (this.selectedDomain || '') : '',
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
