import './bootstrap';

import Alpine from 'alpinejs';
import carousel from './components/carousel';
import contactForm from './components/contact-form';
import linkList from './components/link-list';
import richTextEditor from './components/rich-text-editor';
import tableOfContents from './components/table-of-contents';
import searchPalette from './components/search-palette';
import { securityKeyLogin, securityKeyPasswordless, securityKeyRegister } from './components/webauthn';

window.Alpine = Alpine;

Alpine.data('carousel', carousel);
Alpine.data('contactForm', contactForm);
Alpine.data('linkList', linkList);
Alpine.data('richTextEditor', richTextEditor);
Alpine.data('tableOfContents', tableOfContents);
Alpine.data('searchPalette', searchPalette);
Alpine.data('securityKeyRegister', securityKeyRegister);
Alpine.data('securityKeyLogin', securityKeyLogin);
Alpine.data('securityKeyPasswordless', securityKeyPasswordless);

Alpine.start();

// Mesure d'audience : durée de lecture des pages publiques.
import('./components/page-timer').then(({ default: start }) => start());

// FullCalendar n'est chargé que sur la page des disponibilités.
const availability = document.querySelector('[data-availability-calendar]');
if (availability) {
    import('./availability-calendar').then(({ mountAvailabilityCalendar }) => mountAvailabilityCalendar(availability));
}

// Editor.js n'est chargé que sur les pages qui contiennent un éditeur.
if (document.querySelector('[data-editorjs]')) {
    import('./editor').then(({ mountEditors }) => mountEditors());
}
