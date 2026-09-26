<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Completion\ReissueCertificate;
use App\Actions\Completion\RevokeCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\RevokeCertificateRequest;
use App\Models\Certificate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $certificates = Certificate::query()
            ->with(['student:id,name', 'course:id,title'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('admin.certificates.index', [
            'certificates' => $certificates,
        ]);
    }

    public function revoke(
        RevokeCertificateRequest $request,
        Certificate $certificate,
        RevokeCertificate $revokeCertificate,
    ): RedirectResponse {
        Gate::authorize('revoke', $certificate);
        $revokeCertificate->handle($request->user(), $certificate, $request->validated());

        return redirect()
            ->route('admin.certificates.index')
            ->with('status', 'Certificate revoked. The record is kept for audit.');
    }

    public function reissue(
        Request $request,
        Certificate $certificate,
        ReissueCertificate $reissueCertificate,
    ): RedirectResponse {
        Gate::authorize('reissue', $certificate);
        $replacement = $reissueCertificate->handle($request->user(), $certificate);

        return Redirect::route('admin.certificates.index')
            ->with('status', 'Certificate reissued as '.$replacement->certificate_code.'.');
    }
}
