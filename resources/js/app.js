import './bootstrap';

import Alpine from 'alpinejs';
import carousel from './components/carousel';
import contactForm from './components/contact-form';
import linkList from './components/link-list';
import richTextEditor from './components/rich-text-editor';
import { securityKeyLogin, securityKeyRegister } from './components/webauthn';

window.Alpine = Alpine;

Alpine.data('carousel', carousel);
Alpine.data('contactForm', contactForm);
Alpine.data('linkList', linkList);
Alpine.data('richTextEditor', richTextEditor);
Alpine.data('securityKeyRegister', securityKeyRegister);
Alpine.data('securityKeyLogin', securityKeyLogin);

Alpine.start();

// Editor.js n'est chargé que sur les pages qui contiennent un éditeur.
if (document.querySelector('[data-editorjs]')) {
    import('./editor').then(({ mountEditors }) => mountEditors());
}
