{{-- Dati ufficiali del progetto e della società (config/company.php), uguali a quelli di piramide35.com. --}}
@php($co = config('company'))
<span class="co-foot" style="display:block;margin-top:12px;font-size:.8rem;line-height:1.65;opacity:.85">
    <strong>Hub Core</strong> è un progetto <a href="{{ $co['project_url'] }}" target="_blank" rel="noopener" style="color:inherit;font-weight:700">{{ $co['project'] }}</a>
    · a brevetto di {{ $co['inventor'] }}<br>
    {{ $co['name'] }} · P.IVA {{ $co['vat'] }} · {{ $co['seat']['label'] }}: {{ $co['seat']['street'] }}, {{ $co['seat']['zip'] }} {{ $co['seat']['city'] }} ({{ $co['seat']['province'] }})<br>
    <a href="mailto:{{ $co['email'] }}" style="color:inherit">{{ $co['email'] }}</a> · PEC {{ $co['pec'] }} · Tel. {{ $co['phone'] }}
</span>
