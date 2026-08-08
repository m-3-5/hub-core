<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedbackResponse;
use App\Support\FeedbackQuestions;
use Illuminate\View\View;

class FeedbackAdminController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $responses = FeedbackResponse::with('tenant')->latest()->get();

        $labels = collect($responses)
            ->flatMap(fn (FeedbackResponse $r) => FeedbackQuestions::forTenant($r->tenant))
            ->pluck('label', 'key');

        return view('admin.feedback.index', compact('responses', 'labels'));
    }
}
