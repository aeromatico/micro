import Alpine from 'alpinejs';
import './app.css';

window.Alpine = Alpine;

// Alpine.start() se retrasa a DOMContentLoaded para que los módulos ES de
// página (p.ej. signup.js, que expone window.signupWizard) ya se hayan
// ejecutado — los módulos corren en orden de documento antes de ese evento,
// pero app.js va en <head> y se ejecutaría primero si se llamara aquí mismo.
document.addEventListener('DOMContentLoaded', () => Alpine.start());
