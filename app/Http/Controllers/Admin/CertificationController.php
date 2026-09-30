<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Admin\Concerns\ValidatesPreciseDates;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificationController extends Controller
{
    use HandlesUploads, ValidatesPreciseDates;

    public function index(): View
    {
        return view('admin.certifications.index', ['certifications' => Certification::ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.certifications.form', ['certification' => new Certification(['position' => 0, 'date_precision' => 'year'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $certification = new Certification;
        $certification->fill($this->validated($request, $certification))->save();

        return redirect()->route('admin.certifications.index')->with('success', 'Certification ajoutée.');
    }

    public function edit(Certification $certification): View
    {
        return view('admin.certifications.form', ['certification' => $certification]);
    }

    public function update(Request $request, Certification $certification): RedirectResponse
    {
        $certification->update($this->validated($request, $certification));

        return redirect()->route('admin.certifications.index')->with('success', 'Certification mise à jour.');
    }

    public function destroy(Certification $certification): RedirectResponse
    {
        $this->deletePublicFile($certification->badge_path);
        $certification->delete();

        return back()->with('success', 'Certification supprimée.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Certification $certification): array
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'issuer'         => ['nullable', 'string', 'max:255'],
            'credential_id'  => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'url:http,https', 'max:255'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'position'       => ['nullable', 'integer', 'min:0', 'max:999'],
            'badge'          => $this->imageRules(2048),
        ]);

        $data += $this->preciseDates($request, [
            'issued_at'  => true,
            'expires_at' => false,
        ], ['issued_at', 'expires_at']);

        $data['badge_path'] = $this->syncPublicFile($request, 'badge', $certification->badge_path, 'certifications');
        $data['position'] = (int) ($data['position'] ?? 0);
        unset($data['badge']);

        return $data;
    }
}
