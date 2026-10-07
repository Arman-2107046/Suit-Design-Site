<?php

namespace App\Filament\Support;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Illuminate\Database\Eloquent\Builder;

/*
 * The admin's search box (Ctrl+K / Cmd+K): every searchable screen, as
 * Filament does it, plus customer accounts. Customers have no screen of their
 * own, so a customer leads to their orders, already searched for their email.
 */
class AdminSearchProvider implements GlobalSearchProvider
{
    public function getResults(string $query): ?GlobalSearchResults
    {
        $builder = GlobalSearchResults::make();

        $resources = Filament::getResources();
        usort($resources, fn (string $a, string $b): int => ($a::getGlobalSearchSort() ?? 0) <=> ($b::getGlobalSearchSort() ?? 0));

        foreach ($resources as $resource) {
            if ($resource::canGloballySearch() && ($results = $resource::getGlobalSearchResults($query))->count()) {
                $builder->category($resource::getPluralModelLabel(), $results);
            }

            /* Customers sit right after orders */
            if ($resource === OrderResource::class && ($customers = $this->customers($query))) {
                $builder->category('customers', $customers);
            }
        }

        return $builder;
    }

    /** @return list<GlobalSearchResult> */
    private function customers(string $search): array
    {
        if (! OrderResource::canAccess() || mb_strlen(trim($search)) < 2) {
            return [];
        }

        $term = Search::term($search);

        return User::query()
            ->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term))
            ->withCount('orders')
            ->orderByDesc('orders_count')
            ->limit(5)
            ->get()
            ->map(fn (User $user) => new GlobalSearchResult(
                title: $user->name,
                url: OrderResource::getUrl('index', ['search' => $user->email]),
                details: [
                    'Email' => $user->email,
                    'Orders' => $user->orders_count,
                    'Joined' => $user->created_at?->format('M Y'),
                ],
            ))
            ->all();
    }
}
