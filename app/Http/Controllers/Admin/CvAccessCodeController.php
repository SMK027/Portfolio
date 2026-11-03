<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CvAccessCode;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CvAccessCodeController extends Controller
{
    public function index(): View
    {
        return view('admin.profile.cv-codes', [
            'codes'   => CvAccessCode::latest()->with(['usesLog' => fn ($q) => $q->limit(1)])->get(),
            'profile' => Profile::current(),
        ]);
    }

    /** Historique des téléchargements : date, provenance approximative, appareil. */
    public function show(CvAccessCode $code): View
    {
        return view('admin.profile.cv-code', [
            'code' => $code,
            'uses' => $code->usesLog()->paginate(50),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:150'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'max_uses'   => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $code = CvAccessCode::create(['code' => CvAccessCode::generate()] + $data);

        return back()->with('success', "Code {$code->formatted()} créé pour « {$code->label} ».");
    }

    public function destroy(CvAccessCode $code): RedirectResponse
    {
        $code->delete();

        return back()->with('success', 'Code révoqué.');
    }
}
