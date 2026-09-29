import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import EditorList from '@editorjs/list';
import ImageTool from '@editorjs/image';
import Quote from '@editorjs/quote';
import Code from '@editorjs/code';
import Delimiter from '@editorjs/delimiter';
import Table from '@editorjs/table';
import Warning from '@editorjs/warning';
import Embed from '@editorjs/embed';
import InlineCode from '@editorjs/inline-code';
import Marker from '@editorjs/marker';
import Underline from '@editorjs/underline';
import AlignmentTune from 'editorjs-text-alignment-blocktune';
import ColorPlugin from 'editorjs-text-color-plugin';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const PALETTE = [
    '#0f172a', '#475569', '#dc2626', '#ea580c', '#ca8a04',
    '#16a34a', '#0d9488', '#2563eb', '#4f46e5', '#9333ea', '#db2777',
];

const i18n = {
    messages: {
        ui: {
            blockTunes: { toggler: { 'Click to tune': 'Options', 'or drag to move': 'ou glisser pour déplacer' } },
            inlineToolbar: { converter: { 'Convert to': 'Convertir en' } },
            toolbar: { toolbox: { Add: 'Ajouter' } },
            popover: { Filter: 'Filtrer', 'Nothing found': 'Aucun résultat', 'Convert to': 'Convertir en' },
        },
        toolNames: {
            Text: 'Paragraphe', Heading: 'Titre', 'Unordered List': 'Liste à puces', 'Ordered List': 'Liste numérotée',
            Checklist: 'Liste de tâches', Quote: 'Citation', Code: 'Code', Delimiter: 'Séparateur', Table: 'Tableau',
            Warning: 'Avertissement', Image: 'Image', Link: 'Lien', Bold: 'Gras', Italic: 'Italique',
            InlineCode: 'Code en ligne', Marker: 'Surligner', Underline: 'Souligner', Color: 'Couleur du texte',
            Highlight: 'Surlignage coloré',
        },
        tools: {
            header: { 'Heading 2': 'Titre 2', 'Heading 3': 'Titre 3', 'Heading 4': 'Titre 4' },
            image: {
                Caption: 'Légende', 'Select an Image': 'Choisir une image', 'With border': 'Avec bordure',
                'Stretch image': 'Pleine largeur', 'With background': 'Avec fond',
            },
            link: { 'Add a link': 'Ajouter un lien' },
            list: { Ordered: 'Numérotée', Unordered: 'À puces', Checklist: 'Tâches' },
            quote: { 'Align Left': 'Aligner à gauche', 'Align Center': 'Centrer' },
            table: {
                'With headings': 'Avec en-têtes', 'Without headings': 'Sans en-têtes',
                'Add row above': 'Ligne au-dessus', 'Add row below': 'Ligne en dessous', 'Delete row': 'Supprimer la ligne',
                'Add column to left': 'Colonne à gauche', 'Add column to right': 'Colonne à droite', 'Delete column': 'Supprimer la colonne',
            },
            warning: { Title: 'Titre', Message: 'Message' },
            stub: { 'The block can not be displayed correctly.': 'Ce bloc ne peut pas être affiché.' },
        },
        blockTunes: {
            delete: { Delete: 'Supprimer', 'Click to delete': 'Confirmer la suppression' },
            moveUp: { 'Move up': 'Monter' },
            moveDown: { 'Move down': 'Descendre' },
        },
    },
};

function buildTools(uploadUrl) {
    return {
        alignment: { class: AlignmentTune, config: { default: 'left' } },
        paragraph: { inlineToolbar: true, tunes: ['alignment'] },
        header: {
            class: Header,
            inlineToolbar: true,
            tunes: ['alignment'],
            config: { levels: [2, 3, 4], defaultLevel: 2, placeholder: 'Titre' },
        },
        list: { class: EditorList, inlineToolbar: true, config: { defaultStyle: 'unordered' } },
        quote: { class: Quote, inlineToolbar: true, tunes: ['alignment'] },
        image: {
            class: ImageTool,
            config: {
                endpoints: { byFile: uploadUrl },
                field: 'image',
                types: 'image/png, image/jpeg, image/gif, image/webp',
                additionalRequestHeaders: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                captionPlaceholder: 'Légende',
                buttonContent: 'Choisir une image',
            },
        },
        code: { class: Code, config: { placeholder: 'Votre code…' } },
        table: { class: Table, inlineToolbar: true, config: { rows: 2, cols: 3, withHeadings: true } },
        warning: { class: Warning, inlineToolbar: true, config: { titlePlaceholder: 'Titre', messagePlaceholder: 'Message' } },
        delimiter: Delimiter,
        embed: { class: Embed, config: { services: { youtube: true, vimeo: true, codepen: true } } },
        inlineCode: { class: InlineCode },
        marker: { class: Marker },
        underline: Underline,
        Color: {
            class: ColorPlugin,
            config: { colorCollections: PALETTE, defaultColor: '#dc2626', type: 'text', customPicker: true },
        },
        Highlight: {
            class: ColorPlugin,
            config: { colorCollections: ['#fef08a', '#bbf7d0', '#bfdbfe', '#fbcfe8', '#fed7aa', '#e9d5ff'], defaultColor: '#fef08a', type: 'marker' },
        },
    };
}

/**
 * Monte un éditeur sur chaque élément [data-editorjs].
 *   data-input       : id du champ caché qui reçoit le JSON
 *   data-upload-url  : point de téléversement des images
 *   data-placeholder : texte d'invite
 */
export function mountEditors() {
    const forms = new Map();

    document.querySelectorAll('[data-editorjs]').forEach((holder) => {
        const input = document.getElementById(holder.dataset.input);
        let data;
        try {
            data = input?.value ? JSON.parse(input.value) : undefined;
        } catch {
            data = undefined;
        }

        const editor = new EditorJS({
            holder,
            data,
            i18n,
            minHeight: 120,
            placeholder: holder.dataset.placeholder || 'Commencez à écrire…',
            tools: buildTools(holder.dataset.uploadUrl),
        });

        const form = holder.closest('form');
        if (form && input) {
            if (!forms.has(form)) forms.set(form, []);
            forms.get(form).push({ editor, input });
        }
    });

    // À l'envoi du formulaire, chaque éditeur est sérialisé dans son champ caché.
    forms.forEach((editors, form) => {
        form.addEventListener('submit', async (event) => {
            if (form.dataset.editorReady === '1') return;
            event.preventDefault();
            for (const { editor, input } of editors) {
                input.value = JSON.stringify(await editor.save());
            }
            form.dataset.editorReady = '1';
            form.requestSubmit(event.submitter ?? undefined);
        });
    });
}
