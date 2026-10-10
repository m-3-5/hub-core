<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteLead extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'package', 'message', 'source', 'status', 'ip_hash'];

    public function packageLabel(): string
    {
        $plans = collect(config('landing.plans'))->pluck('name', 'key')->all();

        return $plans[$this->package] ?? match ($this->package) {
            'app' => 'App su misura',
            'altro' => 'Altro / non so ancora',
            default => '—',
        };
    }

    /** Numero pulito per wa.me / tel: (solo cifre, prefisso Italia se manca). */
    public function dialNumber(): string
    {
        $digits = preg_replace('/\D+/', '', $this->phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return strlen($digits) <= 10 && ! str_starts_with($digits, '39') ? '39'.$digits : $digits;
    }
}
