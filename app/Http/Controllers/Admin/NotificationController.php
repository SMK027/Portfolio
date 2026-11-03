<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DiscordNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Notifications Discord (super-administrateurs) : voir App\Services\DiscordNotifier. */
class NotificationController extends Controller
{
    public function __construct(protected DiscordNotifier $discord)
    {
    }

    public function edit(): View
    {
        $settings = $this->discord->settings();

        return view('admin.notifications.edit', [
            'configured' => $settings['webhook'] !== null,
            'webhookHint' => $settings['webhook'] ? '…/'.substr($settings['webhook'], -6) : null,
            'events'     => $settings['events'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'webhook'  => ['nullable', 'string', 'max:300', 'regex:'.DiscordNotifier::WEBHOOK_PATTERN],
            'events'   => ['array'],
            'events.*' => [Rule::in(array_keys(DiscordNotifier::EVENTS))],
            'forget'   => ['nullable', 'boolean'],
        ], [
            'webhook.regex' => 'Collez l\'URL du webhook fournie par Discord (https://discord.com/api/webhooks/…).',
        ]);

        $this->discord->save($data['webhook'] ?? null, $data['events'] ?? [], $request->boolean('forget'));

        return back()->with('success', $request->boolean('forget') ? 'Webhook supprimé : plus aucune notification n\'est envoyée.' : 'Notifications enregistrées.');
    }

    public function test(): RedirectResponse
    {
        $error = $this->discord->test();

        return back()->with($error ? 'error' : 'success', $error ? "Échec de l'envoi : {$error}" : 'Message de test envoyé dans le salon Discord.');
    }
}
