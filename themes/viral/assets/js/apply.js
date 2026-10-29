/* viral. — formulario de postulación (Alpine). El servidor es la fuente de verdad. */
document.addEventListener('alpine:init', () => {
  const PLATFORMS = [
    [/(^|\.)tiktok\.com$/, 'TikTok'], [/(^|\.)(instagram\.com|instagr\.am)$/, 'Instagram'],
    [/(^|\.)(youtube\.com|youtu\.be)$/, 'YouTube'], [/(^|\.)(facebook\.com|fb\.watch|fb\.com)$/, 'Facebook'],
  ];
  const URL_RE = /^(https?:\/\/)?([\w-]+\.)+[a-z]{2,}(\/\S*)?$/i;
  const NAMES = ['Perfil', 'Muestra', 'Cuenta'];

  Alpine.data('viralApply', () => ({
    boot: JSON.parse(document.getElementById('viral-boot').textContent),
    catalog: {}, user: null, app: null,
    step: 1, track: null, level: null, tried1: false, tried2: false,
    links: [''], tags: [], desc: '',
    name: '', email: '', phone: '', department: '', city: '', password: '', showPw: false,
    offer: false, adult: false, terms: false, website: '',
    busy: false, errors: {}, formError: '', poll: null, kycError: '',

    init() {
      this.catalog = this.boot.catalog;
      this.user = this.boot.user;
      this.app = this.boot.application;
      const q = new URLSearchParams(location.search).get('camino');
      if (this.catalog.tracks.some(t => t.k === q)) this.track = q;
      if (this.app) this.showDone();
    },

    get stepName() { return NAMES[this.step - 1] || ''; },
    get trackTags() { return this.catalog.tags[this.track || 'shorts'] || []; },
    get firstName() { const n = (this.user && this.user.name) || this.name; return (n || '').trim().split(/\s+/)[0] || 'crack'; },
    get linkPh() { return { live: 'Pega un live, un replay o tu perfil de TikTok Shop', edit: 'Pega tu portafolio, reel o un video que hayas editado' }[this.track] || 'Pega aquí tu TikTok, Reel, Short o canal'; },
    get linkHelp() { return { live: 'Un live, un replay o tu perfil de ventas.', edit: 'Tu portafolio, tu reel o un video que hayas editado.' }[this.track] || 'Tus videos, tu perfil o tu canal.'; },
    get descPh() { return { live: 'Ej. Vendo moda en TikTok Shop tres veces por semana, lives de 2 horas, y sé manejar el chat y los cupones.', edit: 'Ej. Edito podcasts a clips verticales con subtítulos animados. Premiere y CapCut, entrega en 24 h.' }[this.track] || 'Ej. Convierto podcasts en clips verticales con subtítulos que no te dejan scrollear.'; },
    get doneText() {
      if (!this.app) return '';
      return { approved: '¡Tu perfil fue aprobado!', rejected: 'Por ahora tu perfil no continúa. Gracias por postular.', changes_requested: 'Necesitamos que corrijas algo de tu perfil.' }[this.app.status]
        || 'Tu cuenta Viral ya existe. Revisamos tu perfil y te escribimos pronto.';
    },

    get kycLabel() { return { pending: 'Continuar verificación', rejected: 'Intentar de nuevo' }[this.app && this.app.kyc_status] || 'Verificar mi identidad'; },
    async startKyc() {
      this.busy = true; this.kycError = '';
      try { const r = await this.call('viralApply::onStartKyc', {}); window.location.href = r.url; }
      catch (e) { this.kycError = e.message; this.busy = false; }
    },

    err(f) { return (this.errors[f] || [])[0] || ''; },
    stageState(s) {
      if (s.done) return 'Hecho';
      if (s.state === 'pending') return 'En curso…';
      if (s.state === 'rejected' && s.label.startsWith('Verificación')) return 'Rechazada';
      return 'Pendiente';
    },

    platformTag(u) {
      const s = (u || '').trim().toLowerCase();
      if (!URL_RE.test(s)) return { cls: 'tag-accent', label: 'Eso no parece un link' };
      let host = s.replace(/^https?:\/\//, '').split('/')[0];
      const hit = PLATFORMS.find(([re]) => re.test(host));
      return { cls: hit ? 'tag-neutral' : 'tag-neutral', label: (hit ? hit[1] : 'Otra plataforma') + ' ✓' };
    },
    hasLink() { return this.links.some(l => URL_RE.test(l.trim().toLowerCase())); },

    pickLevel(n) { this.level = n; setTimeout(() => this.next1(), 450); },
    toggleTag(t) { this.tags = this.tags.includes(t) ? this.tags.filter(x => x !== t) : [...this.tags, t]; },
    go(step) { this.step = step; this.$nextTick(() => document.getElementById('flujo').scrollIntoView({ behavior: 'smooth', block: 'start' })); },
    next1() { if (this.track && this.level) this.go(2); else this.tried1 = true; },
    next2() { if (this.hasLink()) this.go(3); else this.tried2 = true; },

    async call(handler, data) {
      const body = new URLSearchParams();
      Object.entries(data || {}).forEach(([k, v]) => {
        if (Array.isArray(v)) v.forEach(x => body.append(k + '[]', x)); else if (v !== null && v !== undefined) body.append(k, v);
      });
      const r = await fetch(location.pathname, {
        method: 'POST', credentials: 'same-origin', body,
        headers: {
          'X-Requested-With': 'XMLHttpRequest', 'X-AJAX-HANDLER': handler,
          'X-CSRF-TOKEN': this.boot.csrf, Accept: 'application/json',
        },
      });
      const j = await r.json().catch(() => ({}));
      const meta = j.__ajax || {};
      if (!r.ok || meta.ok === false) throw { message: meta.message || 'No pudimos procesar tu solicitud. Intenta de nuevo.', fields: Array.isArray(meta.invalid) ? {} : (meta.invalid || {}) };
      return j;
    },

    async submit() {
      if (this.busy) return;
      this.busy = true; this.errors = {}; this.formError = '';
      try {
        const res = await this.call('viralApply::onSubmit', {
          track: this.track, level: this.level, links: this.links.map(l => l.trim()).filter(Boolean),
          tags: this.tags, description: this.desc.trim(), name: this.name.trim(), email: this.email.trim(),
          phone: this.phone, department: this.department, city: this.city.trim(), password: this.password,
          offer: this.offer ? 1 : 0, adult: this.adult ? 1 : 0, terms: this.terms ? 1 : 0, website: this.website,
        });
        this.app = res.application; this.user = res.user || this.user; this.password = '';
        this.showDone();
      } catch (e) {
        this.errors = e.fields || {};
        this.formError = Object.keys(this.errors).length ? 'Revisa los datos marcados.' : e.message;
        if (this.errors.links) { this.go(2); }
      } finally { this.busy = false; }
    },

    showDone() {
      this.go(4);
      clearInterval(this.poll);
      this.poll = setInterval(async () => {
        try { const r = await this.call('viralApply::onStatus', {}); if (r.application) this.app = r.application; } catch (e) {}
      }, new URLSearchParams(location.search).has('kyc') ? 5000 : 30000);
    },
  }));
});
