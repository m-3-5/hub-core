@php
    $contactSuccess = session('contact_success');
@endphp
<div class="pcf">
    <h3 class="pcf-title">Scrivici</h3>
    <p class="pcf-subtitle">Lascia un messaggio, ti rispondiamo via email o telefono.</p>

    @if ($contactSuccess)
        <p class="pcf-success">Messaggio inviato! Ti risponderemo il prima possibile.</p>
    @else
        <form method="POST" action="{{ route('promo.contact', [$tenant, $promo]) }}" class="pcf-form">
            @csrf
            <input type="text" name="website" tabindex="-1" autocomplete="off" class="pcf-honeypot" aria-hidden="true">

            <input type="text" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Il tuo nome" class="pcf-input">
            @error('name')<p class="pcf-error">{{ $message }}</p>@enderror

            <div class="pcf-row">
                <input type="email" name="email" maxlength="190" value="{{ old('email') }}" placeholder="Email" class="pcf-input">
                <input type="tel" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="Telefono" class="pcf-input">
            </div>
            @error('email')<p class="pcf-error">{{ $message }}</p>@enderror
            @error('phone')<p class="pcf-error">{{ $message }}</p>@enderror

            <textarea name="message" rows="3" required maxlength="2000" placeholder="Il tuo messaggio" class="pcf-input pcf-textarea">{{ old('message') }}</textarea>
            @error('message')<p class="pcf-error">{{ $message }}</p>@enderror

            <button type="submit" class="pcf-submit">Invia messaggio</button>
        </form>
    @endif
</div>
