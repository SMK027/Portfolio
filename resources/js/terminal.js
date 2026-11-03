// Mode terminal : le portfolio parcouru en ligne de commande.
// Données fournies par la page (JSON) : seules les sections publiques y figurent.

const SECTION_LABELS = {
    competences: 'Compétences',
    projets: 'Projets',
    veille: 'Articles de veille',
    experiences: 'Expériences',
    formations: 'Formations',
    recommandations: 'Recommandations',
};

const escape = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const link = (url, label) => `<a href="${escape(url)}" class="t-link">${escape(label ?? url)}</a>`;
const externalLink = (url, label) => `<a href="${escape(url)}" class="t-link" target="_blank" rel="noopener noreferrer">${escape(label ?? url)}</a>`;
const dim = (text) => `<span class="t-dim">${escape(text)}</span>`;
const accent = (text) => `<span class="t-accent">${escape(text)}</span>`;

export function mountTerminal(root) {
    const data = JSON.parse(root.querySelector('[data-terminal-data]').textContent);
    const output = root.querySelector('[data-terminal-output]');
    const input = root.querySelector('[data-terminal-input]');
    const promptLabel = root.querySelector('[data-terminal-prompt]');
    const sections = data.sections ?? {};

    let cwd = '~';
    const history = [];
    let historyIndex = 0;

    // Fichiers du dossier personnel
    const files = {
        'a-propos.txt': () => about(),
        'contact.txt': () => contact(),
        ...(data.cv ? { 'cv.pdf': () => openUrl(data.cv, 'Ouverture du CV…') } : {}),
        ...(data.socials.length ? { 'reseaux.txt': () => socials() } : {}),
    };

    const prompt = () => `${data.user}@portfolio:${cwd}$`;
    const updatePrompt = () => { promptLabel.textContent = prompt(); };

    const print = (html = '') => {
        const line = document.createElement('div');
        line.className = 't-line';
        line.innerHTML = html;
        output.appendChild(line);
    };
    const printLines = (lines) => lines.forEach(print);

    const openUrl = (url, message) => {
        print(dim(message ?? `Ouverture de ${url}…`));
        setTimeout(() => { window.location.href = url; }, 350);
    };

    // Éléments d'une section, avec un nom utilisable par cat / open
    const entries = (section) => {
        if (section === 'competences') {
            return Object.keys(sections.competences).map((category) => ({ name: slug(category), label: category }));
        }
        return (sections[section] ?? []).map((item, i) => ({ name: item.name ?? slug(item.title ?? item.author ?? String(i + 1)), label: item.title ?? item.author, item }));
    };
    const slug = (text) => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

    // Résout un chemin (« projets », « ~/veille/mon-article », « ../ ») en [section, entrée]
    const resolve = (path) => {
        const parts = (path ?? '').replace(/^~\/?/, '').split('/').filter((p) => p && p !== '.');
        let section = cwd === '~' || path?.startsWith('~') ? null : cwd.slice(2);
        for (const part of parts) {
            if (part === '..') { section = null; continue; }
            if (! section && sections[part]) { section = part; continue; }
            if (! section && files[part]) { return { file: part }; }
            if (section) {
                const entry = entries(section).find((e) => e.name === part);
                if (entry) { return { section, entry }; }
            }
            return { error: true };
        }
        return { section };
    };

    const about = () => {
        printLines([
            accent(data.name) + (data.headline ? ` — ${escape(data.headline)}` : ''),
            data.location ? dim(`📍 ${data.location}`) : '',
            '',
            ...(data.about ? escape(data.about).split('\n') : [dim('(aucune présentation)')]),
        ].filter((l) => l !== null));
    };

    const contact = () => {
        const lines = [];
        if (data.email) { lines.push(`E-mail   ${link('mailto:' + data.email, data.email)}`); }
        if (data.pages.contact) { lines.push(`Contact  ${link(data.pages.contact.url)}`); }
        if (data.pages['rendez-vous']) { lines.push(`RDV      ${link(data.pages['rendez-vous'].url)}`); }
        printLines(lines.length ? lines : [dim('Aucun moyen de contact public.')]);
    };

    const socials = () => {
        printLines(data.socials.length ? data.socials.map((s) => `${escape(s.name.padEnd(10))} ${externalLink(s.url)}`) : [dim('Aucun réseau.')]);
    };

    const showSection = (section) => {
        if (section === 'competences') {
            Object.entries(sections.competences).forEach(([category, skills]) => {
                print(accent(category));
                print('  ' + skills.map((s) => escape(s.name) + (s.level ? dim(` (${s.level}/5)`) : '')).join(', '));
            });
            return;
        }
        const items = sections[section];
        if (! items.length) { print(dim('(vide)')); return; }
        items.forEach((item) => {
            if (section === 'experiences' || section === 'formations') {
                print(`${accent(item.title)} ${dim(item.period)}`);
                if (item.where) { print('  ' + escape(item.where)); }
            } else if (section === 'recommandations') {
                print(`${accent(item.author)} ${dim(item.title)}`);
            } else {
                print(`${dim((item.date ?? '').padEnd(10))} ${link(item.url, item.title)}`);
            }
        });
    };

    const showEntry = (section, entry) => {
        if (section === 'competences') {
            print(accent(entry.label));
            sections.competences[entry.label].forEach((s) => print(`  • ${escape(s.name)}${s.level ? ' ' + dim('★'.repeat(s.level) + '☆'.repeat(Math.max(0, 5 - s.level))) : ''}`));
            return;
        }
        const item = entry.item;
        if (section === 'recommandations') {
            printLines([accent(item.author) + ' ' + dim(item.title), '', ...escape(item.content).split('\n').map((l) => `  « ${l} »`)]);
            return;
        }
        print(accent(item.title ?? ''));
        if (item.period) { print(dim(item.period)); }
        if (item.where) { print(escape(item.where)); }
        if (item.excerpt) { print(escape(item.excerpt)); }
        if (item.url) { print(`→ ${link(item.url)}  ${dim('(open ' + entry.name + ')')}`); }
    };

    const list = (path) => {
        const target = resolve(path);
        if (target.error || target.entry || target.file) {
            if (target.file || target.entry) { print(escape(path)); return; }
            print(`ls: ${escape(path)} : aucun fichier ou dossier de ce type`);
            return;
        }
        if (! target.section) {
            const dirs = Object.keys(sections).map((s) => `<span class="t-dir">${s}/</span>`);
            print([...dirs, ...Object.keys(files).map(escape)].join('  '));
            return;
        }
        print(entries(target.section).map((e) => escape(e.name)).join('\n').replace(/\n/g, '<br>') || dim('(vide)'));
    };

    const commands = {
        help: {
            description: 'liste des commandes',
            run: () => {
                printLines([
                    'Commandes disponibles :',
                    ...Object.entries(commands).filter(([, c]) => c.description).map(([name, c]) => `  ${accent(name.padEnd(12))} ${escape(c.description)}`),
                    '',
                    dim('Astuces : Tab complète, ↑/↓ parcourent l\'historique, Ctrl+L efface l\'écran.'),
                ]);
            },
        },
        ls: { description: 'contenu du dossier (ls projets)', run: (args) => list(args[0]) },
        cd: {
            description: 'changer de dossier (cd veille, cd ..)',
            run: (args) => {
                const target = resolve(args[0] ?? '~');
                if (target.error || target.entry || target.file) { print(`cd: ${escape(args[0])} : n'est pas un dossier`); return; }
                cwd = target.section ? `~/${target.section}` : '~';
                updatePrompt();
            },
        },
        pwd: { run: () => print(escape(cwd.replace('~', `/home/${data.user}`))) },
        cat: {
            description: 'afficher un fichier ou un élément (cat a-propos.txt)',
            run: (args) => {
                if (! args[0]) { print('cat: nom de fichier attendu'); return; }
                const target = resolve(args[0]);
                if (target.file) { files[target.file](); return; }
                if (target.entry) { showEntry(target.section, target.entry); return; }
                if (target.section) { showSection(target.section); return; }
                print(`cat: ${escape(args[0])} : aucun fichier ou dossier de ce type`);
            },
        },
        open: {
            description: 'ouvrir un élément ou une page du site (open projets)',
            run: (args) => {
                if (! args[0]) { print('open: élément attendu'); return; }
                const target = resolve(args[0]);
                if (target.file) { files[target.file](); return; }
                if (target.entry?.item?.url) { openUrl(target.entry.item.url); return; }
                const page = data.pages[args[0]] ?? (target.section ? data.pages[target.section] : null);
                if (page) { openUrl(page.url); return; }
                print(`open: ${escape(args[0])} : introuvable`);
            },
        },
        whoami: { description: 'qui suis-je ?', run: () => about() },
        contact: { description: 'me contacter', run: () => contact() },
        ...(data.cv ? { cv: { description: 'ouvrir mon CV', run: () => files['cv.pdf']() } } : {}),
        ...(data.socials.length ? { reseaux: { description: 'mes réseaux', run: () => socials() } } : {}),
        ...Object.fromEntries(Object.keys(sections).map((s) => [s, { description: SECTION_LABELS[s].toLowerCase(), run: () => showSection(s) }])),
        neofetch: {
            description: 'résumé du système',
            run: () => {
                const stats = Object.entries(sections).map(([s, v]) => `${SECTION_LABELS[s]}: ${Array.isArray(v) ? v.length : Object.values(v).flat().length}`);
                printLines([
                    `${accent(data.user + '@portfolio')}`,
                    dim('-'.repeat(data.user.length + 10)),
                    `Nom: ${escape(data.name)}`,
                    ...(data.headline ? [`Poste: ${escape(data.headline)}`] : []),
                    ...(data.location ? [`Lieu: ${escape(data.location)}`] : []),
                    `Shell: portfolio-sh 1.0`,
                    ...stats.map(escape),
                ]);
            },
        },
        history: { run: () => printLines(history.map((h, i) => `${dim(String(i + 1).padStart(4))}  ${escape(h)}`)) },
        echo: { run: (args) => print(escape(args.join(' '))) },
        date: { run: () => print(escape(new Date().toLocaleString('fr-FR', { dateStyle: 'full', timeStyle: 'medium' }))) },
        clear: { description: 'effacer l\'écran', run: () => { output.innerHTML = ''; } },
        sudo: { run: () => print(`${escape(data.user)} n'apparaît pas dans le fichier sudoers. Cet incident sera signalé. 😉`) },
        rm: { run: () => print('rm: suppression refusée — le portfolio tient à son contenu.') },
        exit: { description: 'revenir au site', run: () => openUrl(data.home, 'Retour au site…') },
    };
    // Alias (absents de l'aide)
    Object.entries({ gui: 'exit', quit: 'exit', dir: 'ls', type: 'cat', cls: 'clear', about: 'whoami' })
        .forEach(([alias, name]) => { commands[alias] = { run: commands[name].run }; });

    const run = (line) => {
        print(`<span class="t-prompt">${escape(prompt())}</span> ${escape(line)}`);
        const trimmed = line.trim();
        if (! trimmed) { return; }
        history.push(trimmed);
        historyIndex = history.length;

        const [name, ...args] = trimmed.split(/\s+/);
        const command = commands[name.toLowerCase()];
        if (command) {
            command.run(args);
        } else {
            print(`${escape(name)} : commande introuvable. Tapez ${accent('help')}.`);
        }
    };

    const complete = () => {
        const value = input.value;
        const parts = value.split(/\s+/);
        const last = parts[parts.length - 1];
        let candidates;
        if (parts.length === 1) {
            candidates = Object.keys(commands);
        } else {
            const slash = last.lastIndexOf('/');
            const base = slash >= 0 ? last.slice(0, slash + 1) : '';
            const target = resolve(base || '.');
            const pages = Object.keys(data.pages).filter((key) => ! sections[key]);
            const names = target.section ? entries(target.section).map((e) => e.name) : [...Object.keys(sections).map((s) => s + '/'), ...Object.keys(files), ...pages];
            candidates = names.map((n) => base + n);
        }
        const matches = [...new Set(candidates)].filter((c) => c.startsWith(last));
        if (matches.length === 1) {
            parts[parts.length - 1] = matches[0];
            input.value = parts.join(' ') + (matches[0].endsWith('/') ? '' : ' ');
        } else if (matches.length > 1) {
            print(`<span class="t-prompt">${escape(prompt())}</span> ${escape(value)}`);
            print(matches.map(escape).join('  '));
        }
    };

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            run(input.value);
            input.value = '';
        } else if (event.key === 'Tab') {
            event.preventDefault();
            complete();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            historyIndex = Math.max(0, historyIndex - 1);
            input.value = history[historyIndex] ?? '';
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            historyIndex = Math.min(history.length, historyIndex + 1);
            input.value = history[historyIndex] ?? '';
        } else if (event.key === 'l' && event.ctrlKey) {
            event.preventDefault();
            output.innerHTML = '';
        }
        requestAnimationFrame(() => input.scrollIntoView({ block: 'nearest' }));
    });
    root.addEventListener('click', () => { if (! window.getSelection()?.toString()) { input.focus(); } });

    updatePrompt();
    printLines([
        `Bienvenue dans le terminal de ${accent(data.name)}.`,
        `Tapez ${accent('help')} pour la liste des commandes, ${accent('exit')} pour revenir au site.`,
        '',
    ]);
    input.focus();
}
