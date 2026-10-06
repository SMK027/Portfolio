// Calendrier des disponibilités (panel → Rendez-vous → Disponibilités).
// Chargé à la demande : FullCalendar n'alourdit pas les autres pages.
import { Calendar } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';

export function mountAvailabilityCalendar(element) {
    const urls = JSON.parse(element.dataset.urls);
    const editable = element.dataset.editable === '1';
    const notify = (message, type = 'error') => window.dispatchEvent(new CustomEvent('availability-notice', { detail: { message, type } }));
    const errorOf = (error) => error?.response?.data?.message ?? 'Enregistrement impossible.';
    const pad = (n) => String(n).padStart(2, '0');
    const localIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
    const dateOnly = (d) => localIso(d).slice(0, 10);

    const calendar = new Calendar(element, {
        plugins: [timeGridPlugin, interactionPlugin],
        locale: frLocale,
        initialView: window.matchMedia('(max-width: 768px)').matches ? 'timeGridThreeDay' : 'timeGridWeek',
        views: { timeGridThreeDay: { type: 'timeGrid', duration: { days: 3 }, buttonText: '3 jours' } },
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridWeek,timeGridThreeDay' },
        firstDay: 1,
        allDaySlot: true,
        allDayText: 'Jour',
        slotMinTime: '07:00:00',
        slotMaxTime: '21:00:00',
        slotDuration: '00:30:00',
        snapDuration: '00:15:00',
        nowIndicator: true,
        height: 'auto',
        expandRows: true,
        selectable: editable,
        selectMirror: true,
        editable,
        eventOverlap: true,
        events: (info, success, failure) => window.axios
            .get(urls.events, { params: { start: localIso(info.start), end: localIso(info.end) } })
            .then(({ data }) => success(data)).catch(failure),

        // Glisser sur la grille : nouvelle plage (chaque semaine ou ce jour uniquement).
        select: (info) => {
            calendar.unselect();
            if (info.allDay) return;
            window.dispatchEvent(new CustomEvent('availability-choose', { detail: {
                start: localIso(info.start), end: localIso(info.end),
                label: info.start.toLocaleString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
                    + `, ${pad(info.start.getHours())}:${pad(info.start.getMinutes())}–${pad(info.end.getHours())}:${pad(info.end.getMinutes())}`,
                weekday: info.start.toLocaleDateString('fr-FR', { weekday: 'long' }),
            } }));
        },

        // Déplacer / redimensionner une plage.
        eventDrop: (info) => save(info),
        eventResize: (info) => save(info),

        // Clic sur un rendez-vous : détails et décision ; sur un horaire bloqué : détails ;
        // sur une plage : suppression.
        eventClick: (info) => {
            const { kind, key } = info.event.extendedProps;
            if (kind === 'appointment' || kind === 'block') {
                window.dispatchEvent(new CustomEvent(`${kind}-open`, { detail: info.event.extendedProps }));
                return;
            }
            if (! editable || ! ['weekly', 'date'].includes(kind)) return;
            const what = kind === 'weekly' ? 'cette plage hebdomadaire (toutes les semaines)' : 'cette disponibilité ponctuelle';
            if (! window.confirm(`Supprimer ${what} ?`)) return;
            window.axios.delete(urls.range.replace('__KIND__', kind).replace('__ID__', key))
                .then(() => { calendar.refetchEvents(); notify('Plage supprimée.', 'success'); })
                .catch((e) => notify(errorOf(e)));
        },

        // Clic sur l'en-tête d'un jour : fermer / rouvrir la journée.
        navLinks: editable,
        navLinkDayClick: (date) => {
            if (date < new Date(new Date().setHours(0, 0, 0, 0))) return notify('Impossible de fermer un jour passé.');
            window.axios.post(urls.closure, { date: dateOnly(date) })
                .then(({ data }) => { calendar.refetchEvents(); notify(data.closed ? 'Journée fermée : aucun créneau ce jour-là.' : 'Journée rouverte.', 'success'); })
                .catch((e) => notify(errorOf(e)));
        },
        eventContent: (arg) => {
            if (arg.event.display === 'background') return { html: `<span class="fc-closed-label">${arg.event.title}</span>` };
            const time = arg.timeText ? `<div class="fc-event-time">${arg.timeText}</div>` : '';
            return { html: `${time}<div class="fc-event-title">${arg.event.title.replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' })[c])}</div>` };
        },
    });

    function save(info) {
        const { kind, key } = info.event.extendedProps;
        window.axios.put(urls.range.replace('__KIND__', kind).replace('__ID__', key), {
            start: localIso(info.event.start), end: localIso(info.event.end),
        }).then(() => calendar.refetchEvents()).catch((e) => { info.revert(); notify(errorOf(e)); });
    }

    // Choix fait dans la fenêtre « chaque semaine / ce jour uniquement ».
    window.addEventListener('availability-create', ({ detail }) => {
        window.axios.post(urls.store, detail)
            .then(() => { calendar.refetchEvents(); notify('Plage ajoutée.', 'success'); })
            .catch((e) => notify(errorOf(e)));
    });

    // Horaire bloqué ou supprimé depuis les fenêtres de la page.
    window.addEventListener('availability-refresh', () => calendar.refetchEvents());

    calendar.render();
    return calendar;
}
