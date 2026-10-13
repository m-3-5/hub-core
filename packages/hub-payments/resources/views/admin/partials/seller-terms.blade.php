@php
    $termsOk = \M35\HubPayments\Support\SellerTerms::accepted($tenant);
@endphp
@if ($termsOk)
    <p style="margin:0;font-size:.82rem;color:#2e7d32">✓ Condizioni economiche accettate il {{ \Illuminate\Support\Carbon::parse(\M35\HubPayments\Support\SellerTerms::acceptedAt($tenant))->locale('it')->translatedFormat('d F Y') }} · <a href="{{ route('terms.economic') }}" target="_blank">rileggile</a></p>
@else
    @if (\M35\HubPayments\Support\SellerTerms::acceptedAt($tenant))
        <p style="margin:0 0 6px;font-size:.82rem;color:#92400e;font-weight:600">⚠ Abbiamo aggiornato le condizioni economiche (versione {{ \M35\HubPayments\Support\SellerTerms::VERSION }}): rileggile e accettale di nuovo per continuare a vendere con i pagamenti protetti.</p>
    @endif
    <label style="display:flex;gap:8px;align-items:flex-start;font-size:.85rem;color:#374151;line-height:1.4;cursor:pointer">
        <input type="checkbox" name="accept_terms" value="1" required style="margin-top:3px;width:18px;height:18px;flex:none">
        <span>Ho letto e accetto le <a href="{{ route('terms.economic') }}" target="_blank">condizioni economiche</a>: i costi di Stripe, dei rimborsi e delle contestazioni sono a carico mio e vengono scalati dai miei incassi.</span>
    </label>
@endif
