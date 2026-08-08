<?php

namespace App\Http\Controllers;

use App\Models\FeedbackResponse;
use App\Models\Tenant;
use App\Support\FeedbackQuestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function show(Request $request, Tenant $tenant): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('welcome')->withErrors(['feedback' => 'Link non valido o scaduto.']);
        }

        $campaign = $request->string('c', 'general')->toString();

        $already = $tenant->feedbackResponses()->where('campaign', $campaign)->exists();

        return view('feedback.show', [
            'tenant' => $tenant,
            'questions' => FeedbackQuestions::forTenant($tenant),
            'campaign' => $campaign,
            'already' => $already,
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('welcome')->withErrors(['feedback' => 'Link non valido o scaduto.']);
        }

        $campaign = $request->string('c', 'general')->toString();
        $questions = FeedbackQuestions::forTenant($tenant);
        $allowedKeys = collect($questions)->pluck('key')->all();

        $answers = collect($request->input('answers', []))
            ->only($allowedKeys)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (empty($answers)) {
            return back()->withErrors(['answers' => 'Rispondi ad almeno una domanda prima di inviare.']);
        }

        $tenant->feedbackResponses()->create([
            'campaign' => $campaign,
            'answers' => $answers,
        ]);

        return back()->with('feedback_sent', true);
    }
}
