<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InternshipContact;
use App\Models\InternshipRemark;
use App\Services\InternshipSearchExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Suivi de la recherche de stage (administrateurs uniquement). */
class InternshipSearchController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('statut');
        $status = array_key_exists((string) $status, InternshipContact::STATUSES) ? $status : null;

        $all = InternshipContact::ordered()->get();

        return view('admin.internship.index', [
            'contacts' => $status ? $all->where('status', $status) : $all,
            'counts'   => $all->countBy('status'),
            'total'    => $all->count(),
            'status'   => $status,
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

    public function export(string $format, InternshipSearchExport $export): Response
    {
        $contacts = InternshipContact::ordered()->get();
        $remarks = InternshipRemark::ordered()->get();

        return $format === 'pdf' ? $export->pdf($contacts, $remarks) : $export->excel($contacts, $remarks);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $methods = array_keys(InternshipContact::METHODS);

        $data = $request->validate([
            'company'          => ['required', 'string', 'max:150'],
            'address'          => ['required', 'string', 'max:255'],
            'contact_name'     => ['nullable', 'string', 'max:150'],
            'contacted_on'     => ['required', 'date'],
            'contact_method'   => ['required', Rule::in($methods)],
            'status'           => ['required', Rule::in(array_keys(InternshipContact::STATUSES))],
            'follow_up_on'     => ['nullable', 'date', 'after_or_equal:contacted_on'],
            'follow_up_method' => ['nullable', Rule::in($methods)],
            'notes'            => ['nullable', 'string', 'max:5000'],
        ]);

        // La relance n'a de sens que tant qu'une réponse est attendue.
        if (! in_array($data['status'], InternshipContact::AWAITING, true)) {
            $data['follow_up_on'] = $data['follow_up_method'] = null;
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
