<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipContact;
use App\Models\InternshipRemark;
use App\Services\InternshipSearchExport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Suivi de la recherche de stage (administrateurs uniquement). */
class InternshipSearchController extends Controller
{
    /** Tris proposés : clé => [colonne, sens]. */
    protected const SORTS = [
        'recent'    => ['contacted_on', 'desc'],
        'ancien'    => ['contacted_on', 'asc'],
        'relance'   => ['follow_up_on', 'asc'],
        'entreprise' => ['company', 'asc'],
    ];

    /** Libellés des champs (messages d'erreur du tableau et de l'action groupée). */
    public const LABELS = [
        'company' => 'Entreprise', 'address' => 'Adresse', 'contact_name' => 'Interlocuteur',
        'contacted_on' => 'Date de contact', 'contact_method' => 'Moyen de contact', 'status' => 'Résultat',
        'followed_up_on' => 'Relance', 'follow_up_on' => 'Relance prévue', 'notes' => 'Notes',
    ];

    public function index(Request $request): View
    {
        $status = $request->query('statut');
        $status = array_key_exists((string) $status, InternshipContact::STATUSES) ? $status : null;
        $search = trim((string) $request->query('q'));
        $sort = array_key_exists((string) $request->query('tri'), self::SORTS) ? $request->query('tri') : 'recent';

        $all = InternshipContact::ordered()->get();

        $contacts = InternshipContact::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('company', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            // Tri par relance : contacts sans relance prévue en dernier
            ->when($sort === 'relance', fn ($query) => $query->orderByRaw('follow_up_on is null'))
            ->orderBy(...self::SORTS[$sort])->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $answered = $all->whereIn('status', InternshipContact::CLOSED)->count(); // « Sans réponse » vaut refus

        return view('admin.internship.index', [
            'contacts' => $contacts,
            'counts'   => $all->countBy('status'),
            'stats'    => [
                'total'     => $all->count(),
                'awaiting'  => $all->filter->isAwaiting()->count(),
                'due'       => $all->filter->followUpDue()->count(),
                'accepted'  => $all->where('status', 'accepted')->count(),
                'responses' => $all->count() ? (int) round($answered / $all->count() * 100) : 0,
            ],
            'status'   => $status,
            'search'   => $search,
            'sort'     => $sort,
            'grouped'  => in_array($sort, ['recent', 'ancien'], true), // lignes regroupées par date de contact
            'sorts'    => ['recent' => 'Plus récents', 'ancien' => 'Plus anciens', 'relance' => 'Prochaine relance', 'entreprise' => 'Entreprise (A → Z)'],
            'remarks'  => InternshipRemark::ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.internship.form', ['contact' => new InternshipContact([
            'contacted_on' => today(),
            'status'       => 'pending',
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        InternshipContact::create($this->validated($request));

        return redirect()->route('admin.internship.index')->with('success', 'Contact ajouté.');
    }

    public function edit(InternshipContact $contact): View
    {
        return view('admin.internship.form', ['contact' => $contact]);
    }

    public function update(Request $request, InternshipContact $contact): RedirectResponse
    {
        $contact->update($this->validated($request));

        return redirect()->route('admin.internship.index')->with('success', 'Contact mis à jour.');
    }

    public function destroy(InternshipContact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('success', 'Contact supprimé.');
    }

    public function storeRemark(Request $request): RedirectResponse
    {
        InternshipRemark::create($this->validatedRemark($request));

        return redirect()->route('admin.internship.index')->with('success', 'Remarque ajoutée.');
    }

    public function updateRemark(Request $request, InternshipRemark $remark): RedirectResponse
    {
        $remark->update($this->validatedRemark($request));

        return redirect()->route('admin.internship.index')->with('success', 'Remarque mise à jour.');
    }

    public function destroyRemark(InternshipRemark $remark): RedirectResponse
    {
        $remark->delete();

        return back()->with('success', 'Remarque supprimée.');
    }

    /** Export PDF ou Excel ; ?champs[]=… limite les colonnes du tableau des contacts. */
    public function export(Request $request, string $format, InternshipSearchExport $export): Response
    {
        $contacts = InternshipContact::ordered()->get();
        $remarks = InternshipRemark::ordered()->get();
        $fields = (array) $request->query('champs', []);
        $export->titled(is_string($request->query('titre')) ? $request->query('titre') : null);

        return $format === 'pdf' ? $export->pdf($contacts, $remarks, $fields) : $export->excel($contacts, $remarks, $fields);
    }

    /** Action groupée sur les contacts cochés : mêmes valeurs appliquées à tous, ou suppression. */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate([
            'ids'    => ['required', 'array', 'max:500'],
            'ids.*'  => ['integer'],
            'action' => ['required', Rule::in(['update', 'delete'])],
        ], ['ids.required' => 'Sélectionnez au moins un contact.']);

        $contacts = InternshipContact::whereKey($request->input('ids'))->get();
        abort_if($contacts->isEmpty(), 404);

        if ($request->input('action') === 'delete') {
            abort_unless($request->user()->can('panel', 'internship.delete'), 403);
            $contacts->each->delete();

            return back()->with('success', $contacts->count().' contact(s) supprimé(s).');
        }

        abort_unless($request->user()->can('panel', 'internship.update'), 403);

        // Champ vide : valeur inchangée ; case « effacer » : date remise à vide.
        $changes = array_filter($request->validate([
            'status'         => ['nullable', Rule::in(array_keys(InternshipContact::STATUSES))],
            'contact_method' => ['nullable', Rule::in(array_keys(InternshipContact::METHODS))],
            'followed_up_on' => ['nullable', 'date'],
            'follow_up_on'   => ['nullable', 'date'],
        ]), fn ($value) => $value !== null);
        foreach (['followed_up_on', 'follow_up_on'] as $field) {
            if ($request->boolean("clear_{$field}")) {
                $changes[$field] = null;
            }
        }

        if (! $changes) {
            return back()->withErrors(['bulk' => 'Aucune modification à appliquer.']);
        }

        // Chaque contact doit rester valide (relances postérieures à la date de contact).
        $rows = $contacts->mapWithKeys(fn ($contact) => [$contact->id => array_merge($this->formData($contact), $changes)]);
        $this->validateRows($rows->all(), $contacts);

        DB::transaction(fn () => $contacts->each(fn ($contact) => $contact->update($this->normalized($rows[$contact->id]))));

        return back()->with('success', $contacts->count().' contact(s) mis à jour.');
    }

    /** Tableau éditable : tous les contacts modifiables ligne par ligne. */
    public function grid(): View
    {
        return view('admin.internship.grid', ['contacts' => InternshipContact::ordered()->get()]);
    }

    public function updateGrid(Request $request): RedirectResponse
    {
        $request->validate(['contacts' => ['required', 'array']], ['contacts.required' => 'Aucune ligne modifiée.']);

        $contacts = InternshipContact::whereKey(array_keys($request->input('contacts')))->get()->keyBy('id');
        $rows = array_intersect_key($request->input('contacts'), $contacts->all());
        $this->validateRows($rows, $contacts);

        $updated = DB::transaction(fn () => collect($rows)->filter(function ($row, $id) use ($contacts) {
            $contact = $contacts[$id]->fill($this->normalized(Arr::only($row, array_keys($this->rules()))));

            return $contact->isDirty() && $contact->save();
        })->count());

        return redirect()->route('admin.internship.index')->with('success', $updated.' contact(s) mis à jour.');
    }

    /** Règles d'un contact (formulaire, tableau, action groupée). */
    protected function rules(string $prefix = ''): array
    {
        return [
            $prefix.'company'        => ['required', 'string', 'max:150'],
            $prefix.'address'        => ['required', 'string', 'max:255'],
            $prefix.'contact_name'   => ['nullable', 'string', 'max:150'],
            $prefix.'contacted_on'   => ['required', 'date'],
            $prefix.'contact_method' => ['required', Rule::in(array_keys(InternshipContact::METHODS))],
            $prefix.'status'         => ['required', Rule::in(array_keys(InternshipContact::STATUSES))],
            $prefix.'followed_up_on' => ['nullable', 'date', 'after_or_equal:'.$prefix.'contacted_on'],
            $prefix.'follow_up_on'   => ['nullable', 'date', 'after_or_equal:'.$prefix.'contacted_on'],
            $prefix.'notes'          => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Valide plusieurs contacts à la fois ; les erreurs sont nommées d'après l'entreprise.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, InternshipContact>  $contacts
     */
    protected function validateRows(array $rows, Collection $contacts): void
    {
        $attributes = [];
        foreach ($rows as $id => $row) {
            foreach (array_keys($this->rules()) as $field) {
                $attributes["contacts.{$id}.{$field}"] = ($contacts->firstWhere('id', $id)?->company ?? "#{$id}").' — '
                    .mb_strtolower(self::LABELS[$field]);
            }
        }

        Validator::make(['contacts' => $rows], $this->rules('contacts.*.'), [], $attributes)->validate();
    }

    /** @return array<string, mixed> */
    protected function formData(InternshipContact $contact): array
    {
        return array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value,
            $contact->only(array_keys($this->rules())));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $this->normalized($request->validate($this->rules()));
    }

    /**
     * La dernière relance est conservée quel que soit le résultat ; la relance prévue
     * n'a de sens que tant qu'une réponse est attendue.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalized(array $data): array
    {
        if (isset($data['status']) && ! in_array($data['status'], InternshipContact::AWAITING, true)) {
            $data['follow_up_on'] = null;
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function validatedRemark(Request $request): array
    {
        return $request->validateWithBag('remark', [
            'noted_on' => ['required', 'date'],
            'body'     => ['required', 'string', 'max:5000'],
        ]);
    }
}
