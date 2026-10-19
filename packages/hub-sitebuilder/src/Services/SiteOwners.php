<?php

namespace M35\HubSitebuilder\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/** Chi riceve le email del sito: solo titolari con una email vera (mai gli account ospite @guest.hub-core.local). */
class SiteOwners
{
    /** @return Collection<int, User> */
    public static function for(Tenant $tenant): Collection
    {
        return $tenant->users->filter(fn (User $u) => $u->email && ! str_ends_with(strtolower($u->email), '@guest.hub-core.local'))->values();
    }
}
