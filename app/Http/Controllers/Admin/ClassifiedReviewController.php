<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedAd;
use App\Notifications\ClassifiedReviewedNotification;
use App\Support\CreditNotices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Throwable;

/** Max controlla gli annunci dei privati prima che vadano online (30 giorni). */
class ClassifiedReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return view('admin.classifieds.review', [
            'ads' => ClassifiedAd::with('tenant')->where('status', 'review')->oldest()->get(),
        ]);
    }

    public function approve(Request $request, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $classifiedAd->update([
            'status' => 'published',
            'published_at' => now(),
            'expires_at' => now()->addDays(ClassifiedAd::PRIVATE_DAYS),
        ]);
        $this->tell($classifiedAd, true);

        return back()->with('success', 'Online: «'.$classifiedAd->title.'».');
    }

    public function reject(Request $request, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $reason = trim((string) $request->validate(['reason' => ['nullable', 'string', 'max:300']])['reason'] ?? '') ?: null;

        $classifiedAd->update(['status' => 'rejected']);
        $this->tell($classifiedAd, false, $reason);

        return back()->with('success', 'Rifiutato: «'.$classifiedAd->title.'».');
    }

    private function tell(ClassifiedAd $ad, bool $approved, ?string $reason = null): void
    {
        try {
            $email = $ad->contact_email ?: CreditNotices::owners($ad->tenant)->first()?->email;
            if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $email)->notify(new ClassifiedReviewedNotification($ad->fresh('tenant'), $approved, $reason));
            }
        } catch (Throwable $e) {
            Log::warning('Esito annuncio: email non inviata', ['ad' => $ad->id, 'message' => $e->getMessage()]);
        }
    }
}
