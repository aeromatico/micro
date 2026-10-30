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
    step: 1, track: null, level: null, rateModel: '', rateAmount: '', tried1: false, tried2: false,
    phoneOk: false, otpSent: false, otp: '', otpBusy: false, otpErr: '', cooldown: 0, _cd: null, _phoneChecked: '',
    photoId: '', photoUrl: '', photoBusy: false, photoErr: '',
    method: 'link', uploads: [], recording: false, recFallback: false, recError: '', secs: 0, _rec: null, _timer: null, _n: 0,
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
    // Foto de perfil: se reduce en el navegador (respeta la orientación del teléfono) y se sube por el servidor.
    async pickPhoto(ev) {
      const f = ev.target.files[0]; ev.target.value = '';
      if (!f) return;
      this.photoErr = '';
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { this.photoErr = 'Sube una foto JPG, PNG o WebP.'; return; }
      this.photoBusy = true;
      try {
        const bmp = await createImageBitmap(f, { imageOrientation: 'from-image' });
        const k = Math.min(1, 800 / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas'); c.width = Math.round(bmp.width * k); c.height = Math.round(bmp.height * k);
        const g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height); g.drawImage(bmp, 0, 0, c.width, c.height);
        const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.85));
        const fd = new FormData(); fd.append('photo', blob, 'perfil.jpg');
        const r = await fetch(location.pathname, { method: 'POST', credentials: 'same-origin', body: fd,
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-AJAX-HANDLER': 'viralApply::onPhoto', 'X-CSRF-TOKEN': this.boot.csrf, Accept: 'application/json' } });
        const j = await r.json().catch(() => ({})); const meta = j.__ajax || {};
        if (!r.ok || meta.ok === false || !j.id) throw new Error(meta.message || 'No pudimos subir la foto. Prueba con otra.');
        if (this.photoUrl) URL.revokeObjectURL(this.photoUrl);
        this.photoId = j.id; this.photoUrl = URL.createObjectURL(blob);
      } catch (e) { this.photoErr = e.message || 'No pudimos leer esa foto.'; }
      finally { this.photoBusy = false; }
    },
    removePhoto() { if (this.photoUrl) URL.revokeObjectURL(this.photoUrl); this.photoId = ''; this.photoUrl = ''; },
    phoneDigits() { return this.phone.replace(/\D+/g, '').replace(/^(00)?591/, ''); },
    phoneValid() { return /^[67]\d{7}$/.test(this.phoneDigits()); },
    phoneChanged() { if (this.phoneDigits() !== this._phoneChecked) { this.phoneOk = false; this.otpSent = false; this.otp = ''; this.otpErr = ''; } },
    async sendOtp() {
      this.otpBusy = true; this.otpErr = '';
      try {
        const r = await this.call('viralApply::onPhoneSend', { phone: this.phone });
        this._phoneChecked = this.phoneDigits(); this.otpSent = true; this.otp = '';
        this.cooldown = r.cooldown || 60; clearInterval(this._cd);
        this._cd = setInterval(() => { if (--this.cooldown <= 0) clearInterval(this._cd); }, 1000);
        this.$nextTick(() => document.getElementById('f-otp')?.focus());
      } catch (e) { this.otpErr = e.message; } finally { this.otpBusy = false; }
    },
    async checkOtp() {
      if (this.otp.length !== 6) return;
      this.otpBusy = true; this.otpErr = '';
      try {
        await this.call('viralApply::onPhoneVerify', { phone: this.phone, code: this.otp });
        this.phoneOk = true; this.otpSent = false; clearInterval(this._cd);
      } catch (e) { this.otpErr = e.message; } finally { this.otpBusy = false; }
    },
    hasSample() { return this.hasLink() || this.uploads.some(u => u.status === 'done'); },
    uploading() { return this.uploads.some(u => u.status === 'uploading'); },
    baseMime(t) { return (t || '').split(';')[0].trim().toLowerCase(); },
    pickFiles(ev, kind) {
      [...ev.target.files].forEach(f => this.addFile(f, kind || 'file', 0));
      ev.target.value = '';
    },
    addFile(file, kind, duration) {
      const mime = this.baseMime(file.type) || (/\.mov$/i.test(file.name) ? 'video/quicktime' : '');
      const OK = ['video/mp4', 'video/quicktime', 'video/webm', 'video/x-m4v', 'image/jpeg', 'image/png', 'image/webp'];
      const u = { key: ++this._n, name: file.name || 'grabación', file, mime, kind, duration, status: 'uploading', pct: 0, err: '', uid: null, xhr: null };
      if (this.uploads.length >= 3) { u.status = 'error'; u.err = 'Máximo 3 archivos.'; }
      else if (!OK.includes(mime)) { u.status = 'error'; u.err = 'Formato no admitido (usa MP4, MOV o WebM).'; }
      else if (file.size > 100 * 1024 * 1024) { u.status = 'error'; u.err = 'Pesa más de 100 MB.'; }
      else if (file.size < 1) { u.status = 'error'; u.err = 'El archivo está vacío.'; }
      this.uploads.push(u);
      if (u.status === 'uploading') this.startUpload(this.uploads[this.uploads.length - 1]);
    },
    async startUpload(u) {
      u.status = 'uploading'; u.pct = 0; u.err = '';
      try {
        const init = await this.call('viralApply::onUploadInit', { mime: u.mime, size: u.file.size, kind: u.kind });
        u.uid = init.id;
        const fd = new FormData();
        Object.entries(init.fields).forEach(([k, v]) => fd.append(k, v));
        fd.append('file', u.file);
        await new Promise((resolve, reject) => {
          const x = new XMLHttpRequest(); u.xhr = x;
          x.open('POST', init.url);
          x.upload.onprogress = e => { if (e.lengthComputable) u.pct = Math.round(e.loaded * 100 / e.total); };
          x.onload = () => (x.status >= 200 && x.status < 300) ? resolve() : reject(new Error('rechazado'));
          x.onerror = () => reject(new Error('red'));
          x.onabort = () => reject(new Error('cancelado'));
          x.send(fd);
        });
        u.pct = 100; u.status = 'done';
      } catch (e) {
        if (!this.uploads.includes(u)) return;
        u.status = 'error'; u.err = e.message === 'red' ? 'Se cortó la conexión.' : (e.message === 'rechazado' ? 'No se pudo subir.' : e.message);
      }
    },
    removeUpload(u) { if (u.xhr) u.xhr.abort(); this.uploads = this.uploads.filter(x => x !== u); },
    async startRecord() {
      this.recError = ''; this.recFallback = false;
      if (!window.MediaRecorder || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { this.recFallback = true; this.recError = 'Tu navegador no graba desde aquí. Usa la cámara del teléfono.'; return; }
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 1280 } }, audio: true });
        const type = ['video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm'].find(t => MediaRecorder.isTypeSupported(t)) || '';
        const rec = new MediaRecorder(stream, type ? { mimeType: type } : {});
        const chunks = [];
        rec.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
        rec.onstop = () => {
          clearInterval(this._timer); stream.getTracks().forEach(t => t.stop());
          this.recording = false; this.$refs.live.srcObject = null;
          const mime = this.baseMime(rec.mimeType) || 'video/webm';
          const ext = mime === 'video/mp4' ? 'mp4' : 'webm';
          if (chunks.length) this.addFile(new File([new Blob(chunks, { type: mime })], 'grabacion.' + ext, { type: mime }), 'recording', this.secs);
        };
        this.$refs.live.srcObject = stream; this.$refs.live.play();
        this._rec = rec; this.secs = 0; this.recording = true; rec.start(1000);
        this._timer = setInterval(() => { this.secs++; if (this.secs >= 60) this.stopRecord(); }, 1000);
      } catch (e) {
        this.recFallback = true;
        this.recError = (e && e.name === 'NotAllowedError') ? 'Necesitamos permiso para usar la cámara y el micrófono. También puedes abrir la cámara del teléfono.' : 'No pudimos acceder a la cámara. Prueba con la cámara del teléfono.';
      }
    },
    stopRecord() { if (this._rec && this._rec.state !== 'inactive') this._rec.stop(); },
    hasLink() { return this.links.some(l => URL_RE.test(l.trim().toLowerCase())); },

    pickTrack(k) { if (this.track !== k) { this.track = k; this.rateModel = ''; this.rateAmount = ''; if (this.level) this.tried1 = false; } },
    levelRate(n) {
      if (this.track === 'live') return 'Tú fijas: por hora o comisión por venta';
      if (this.track === 'edit') return 'Tú fijas tu tarifa por proyecto';
      const r = this.catalog.rates;
      if (n === 1) return 'Bs ' + r.shorts_fixed + ' por segundo';
      if (n === 2) return 'Bs ' + r.shorts_range[0] + ' a ' + r.shorts_range[1] + ' por segundo';
      return 'Elige tu tarifa';
    },
    needsRate() { return (this.track === 'shorts' && this.level >= 3) || this.track === 'live'; },
    rateErr() {
      if (!this.tried1 || !this.needsRate()) return this.errors.rate_amount || this.errors.rate_model || '';
      if (this.track === 'live' && !this.rateModel) return 'Elige cómo quieres cobrar.';
      const v = parseFloat(this.rateAmount);
      if (!(v > 0)) return this.track === 'live' && this.rateModel === 'commission_percent' ? 'Escribe un porcentaje entre 1 y 100.' : 'Escribe tu tarifa.';
      if (this.rateModel === 'commission_percent' && v > 100) return 'Escribe un porcentaje entre 1 y 100.';
      return this.errors.rate_amount || this.errors.rate_model || '';
    },
    pickLevel(n) { this.level = n; this.tried1 = false; this.$nextTick(() => document.getElementById("tarifa")?.scrollIntoView({ behavior: "smooth", block: "nearest" })); },
    toggleTag(t) { this.tags = this.tags.includes(t) ? this.tags.filter(x => x !== t) : [...this.tags, t]; },
    go(step) { this.step = step; this.$nextTick(() => document.getElementById('flujo').scrollIntoView({ behavior: 'smooth', block: 'start' })); },
    next1() { this.tried1 = true; if (this.track && this.level && !this.rateErr()) this.go(2); },
    next2() { this.tried2 = true; if (this.hasSample() && !this.uploading()) this.go(3); },

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
          track: this.track, level: this.level, rate_model: this.rateModel, rate_amount: this.rateAmount, links: this.links.map(l => l.trim()).filter(Boolean),
          uploads: this.uploads.filter(u => u.status === 'done').map(u => ({ id: u.uid, duration: u.duration || 0 })),
          tags: this.tags, description: this.desc.trim(), name: this.name.trim(), email: this.email.trim(),
          phone: this.phone, department: this.department, city: this.city.trim(), password: this.password,
          offer: this.offer ? 1 : 0, photo_id: this.offer ? this.photoId : '', adult: this.adult ? 1 : 0, terms: this.terms ? 1 : 0, website: this.website,
        });
        this.app = res.application; this.user = res.user || this.user; this.password = '';
        this.showDone();
      } catch (e) {
        this.errors = e.fields || {};
        this.formError = Object.keys(this.errors).length ? 'Revisa los datos marcados.' : e.message;
        if (this.errors.links) { this.go(2); }
        if (this.errors.rate_amount || this.errors.rate_model) { this.go(1); }
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
