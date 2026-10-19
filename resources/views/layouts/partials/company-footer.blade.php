{{-- Dati ufficiali del progetto e della società (config/company.php), uguali a quelli di piramide35.com. In breve: una riga + «Dati societari» a scomparsa. --}}
@php($co = config('company'))
<span class="co-foot">
    <span class="co-foot__line"><b>{{ $co['name'] }}</b> · P.IVA {{ $co['vat'] }} · {{ $co['seat']['city'] }} ({{ $co['seat']['province'] }}) · <a href="mailto:{{ $co['email'] }}">{{ $co['email'] }}</a></span>
    <details class="co-foot__more">
        <summary>Dati societari</summary>
        <span><strong>Hub Core</strong> è un progetto <a href="{{ $co['project_url'] }}" target="_blank" rel="noopener">{{ $co['project'] }}</a> a brevetto di {{ $co['inventor'] }}.<br>
        {{ $co['seat']['label'] }}: {{ $co['seat']['street'] }}, {{ $co['seat']['zip'] }} {{ $co['seat']['city'] }} ({{ $co['seat']['province'] }}) · PEC {{ $co['pec'] }} · Tel. {{ $co['phone'] }}</span>
    </details>
</span>
