import './bootstrap';

import Alpine from 'alpinejs';
import carousel from './components/carousel';
import contactForm from './components/contact-form';
import linkList from './components/link-list';

window.Alpine = Alpine;

Alpine.data('carousel', carousel);
Alpine.data('contactForm', contactForm);
Alpine.data('linkList', linkList);

Alpine.start();

// Editor.js n'est chargé que sur les pages qui contiennent un éditeur.
if (document.querySelector('[data-editorjs]')) {
    import('./editor').then(({ mountEditors }) => mountEditors());
}
