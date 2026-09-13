function signupWizard(config) {
    return {
        niches: config.niches || [],
        plans: config.plans || {},
        signupEnabled: !!config.signupEnabled,

        step: 1,
        handle: '',
        niche: '',
        plan: config.initialPlan && config.plans[config.initialPlan] ? config.initialPlan : 'negocio',

        checking: false,
        available: null,
        statusMessage: '',
        _checkTimer: null,

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
                data: { handle: this.handle, niche: this.niche, plan: this.plan },
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
