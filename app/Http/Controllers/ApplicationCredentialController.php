<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationCredential;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationCredentialController extends Controller
{
    public function store(Request $request, Application $application): RedirectResponse
    {
        $secret = Str::random(64);
        $credential = $application->credentials()->create([
            'client_id' => 'cli_'.Str::lower(Str::random(24)),
            'encrypted_secret' => $secret,
            'valid_from' => now(),
            'is_active' => true,
        ]);
        $this->audit($request, 'credential.generated', $credential);

        return redirect()->route('applications.show', $application)->with('new_credential', [
            'client_id' => $credential->client_id,
            'secret' => $secret,
        ]);
    }

    public function rotate(Request $request, Application $application): RedirectResponse
    {
        $secret = Str::random(64);
        $credential = DB::transaction(function () use ($application, $secret): ApplicationCredential {
            $application->credentials()->where('is_active', true)->whereNull('expires_at')
                ->update(['expires_at' => now()->addHours(config('sivera.rotation_grace_hours'))]);

            return $application->credentials()->create([
                'client_id' => 'cli_'.Str::lower(Str::random(24)),
                'encrypted_secret' => $secret,
                'valid_from' => now(),
                'is_active' => true,
            ]);
        });
        $this->audit($request, 'credential.rotated', $credential);

        return redirect()->route('applications.show', $application)->with('new_credential', [
            'client_id' => $credential->client_id,
            'secret' => $secret,
        ]);
    }

    public function revoke(Request $request, Application $application, ApplicationCredential $credential): RedirectResponse
    {
        abort_unless($credential->application_id === $application->id, 404);
        $credential->update(['is_active' => false, 'revoked_at' => now()]);
        $this->audit($request, 'credential.revoked', $credential);

        return back()->with('status', 'Credential revoked.');
    }

    private function audit(Request $request, string $action, ApplicationCredential $credential): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => ApplicationCredential::class,
            'subject_id' => $credential->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
