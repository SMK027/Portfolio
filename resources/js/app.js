import './bootstrap';

import Alpine from 'alpinejs';
import carousel from './components/carousel';
import contactForm from './components/contact-form';
import linkList from './components/link-list';
import richTextEditor from './components/rich-text-editor';
import tableOfContents from './components/table-of-contents';
import readingProgress from './components/reading-progress';
import articleReactions from './components/article-reactions';
import sandboxTerminal from './components/sandbox-terminal';
import searchPalette from './components/search-palette';
import availabilityBlocks from './components/availability-blocks';
import availabilityRange from './components/availability-range';
import adminPalette from './components/admin-palette';
import { securityKeyLogin, securityKeyPasswordless, securityKeyRegister } from './components/webauthn';

window.Alpine = Alpine;

Alpine.data('carousel', carousel);
Alpine.data('contactForm', contactForm);
Alpine.data('linkList', linkList);
Alpine.data('richTextEditor', richTextEditor);
Alpine.data('tableOfContents', tableOfContents);
Alpine.data('readingProgress', readingProgress);
Alpine.data('articleReactions', articleReactions);
Alpine.data('sandboxTerminal', sandboxTerminal);
Alpine.data('searchPalette', searchPalette);
Alpine.data('availabilityBlocks', availabilityBlocks);
Alpine.data('availabilityRange', availabilityRange);
Alpine.data('adminPalette', adminPalette);
Alpine.data('securityKeyRegister', securityKeyRegister);
Alpine.data('securityKeyLogin', securityKeyLogin);
Alpine.data('securityKeyPasswordless', securityKeyPasswordless);

Alpine.start();

// Mesure d'audience : durée de lecture des pages publiques.
import('./components/page-timer').then(({ default: start }) => start());

// Easter eggs : déclencheurs cachés (toutes les pages publiques) et page secrète.
if (document.getElementById('easter-egg-triggers')) {
    import('./easter-eggs/triggers').then(({ default: start }) => start());
}
if (document.querySelector('[data-easter-eggs]')) {
    import('./easter-eggs/page').then(({ default: start }) => start());
}

// Application installable : service worker (visiteurs) et page hors connexion.
import('./pwa').then(({ default: startPwa, listOfflinePages }) => {
    startPwa();
    const offlineList = document.querySelector('[data-offline-list]');
    if (offlineList) {
        listOfflinePages(offlineList);
    }
});

// Mode terminal : la page /terminal, et le raccourci ² (ou `) depuis les pages publiques.
const terminal = document.querySelector('[data-terminal]');
if (terminal) {
    import('./terminal').then(({ mountTerminal }) => mountTerminal(terminal));
}
const terminalUrl = document.body.dataset.terminalUrl;
if (terminalUrl) {
    document.addEventListener('keydown', (event) => {
        const typing = event.target.closest?.('input, textarea, select, [contenteditable]');
        if (! typing && ! event.ctrlKey && ! event.metaKey && ! event.altKey && (event.key === '²' || event.key === '`')) {
            window.location.href = terminalUrl;
        }
    });
}

// FullCalendar n'est chargé que sur la page des disponibilités.
const availability = document.querySelector('[data-availability-calendar]');
if (availability) {
    import('./availability-calendar').then(({ mountAvailabilityCalendar }) => mountAvailabilityCalendar(availability));
}

// Editor.js n'est chargé que sur les pages qui contiennent un éditeur.
if (document.querySelector('[data-editorjs]')) {
    import('./editor').then(({ mountEditors }) => mountEditors());
}
