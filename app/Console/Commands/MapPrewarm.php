<?php

namespace App\Console\Commands;

use App\Support\TeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class MapPrewarm extends Command
{
    protected $signature   = 'map:prewarm {locale?}';
    protected $description = 'Pre-warm te_prop:{slug} cache for all map properties (per locale)';

    public function handle(): int
    {
        // Cache and API content are localized (CRM-441); prewarm the language the
        // triggering request ran under so its clicks open instantly.
        $locale = in_array($this->argument('locale'), ['en', 'ru', 'hy'], true)
            ? $this->argument('locale')
            : config('app.locale');
        App::setLocale($locale);

        $result = Cache::get(TeCache::key('te_map_list', $locale));
        if (!$result) {
            $this->error('te_map_list cache is empty — visit /map/data first');
            return 1;
        }

        $items = $result['items'] ?? [];
        $warmed = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $slug = $item['slug'] ?? null;
            if (!$slug) continue;

            if (Cache::has(TeCache::prop($slug, $locale))) {
                $skipped++;
                continue;
            }

            try {
                Cache::remember(TeCache::prop($slug, $locale), 3600, function () use ($slug) {
                    $client   = app(\TouchEstate\Sdk\TouchEstateClient::class);
                    $property = $client->properties()->retrieve($slug);
                    $property['slug'] = $slug;
                    $addrParts = array_filter([
                        $property['street']         ?? null,
                        $property['buildingNumber'] ?? null,
                        $property['district']       ?? null,
                        $property['city']           ?? null,
                        $property['country']        ?? null,
                    ]);
                    if ($addrParts) {
                        $property['fullAddress'] = implode(', ', $addrParts);
                    }
                    return $property;
                });
                $warmed++;
            } catch (\Exception $e) {
                // skip failed slugs silently
            }
        }

        Cache::forget('map_prewarm_running');
        $this->info("Done. Warmed: {$warmed}, skipped (already cached): {$skipped}");
        return 0;
    }
}
