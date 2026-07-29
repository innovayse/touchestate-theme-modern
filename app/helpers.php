<?php

if (!function_exists('geo_localized_name')) {
    /**
     * Look up a localized place name in a geo-atlas translation map.
     *
     * The TouchEstate API returns city/country as single English strings
     * ("Yerevan", "Armenia"). Translations come exclusively from @innovayse/geo-atlas
     * (our own offline dataset), extracted into resources/data/{dataset}-translations.json.
     * A name not in geo-atlas simply shows its English form — the dataset is the single
     * source of truth and is fixed upstream in the package.
     *
     * $dataset: 'city' | 'country'. Maps are parsed once and cached 24h.
     * Regenerate them with: npm run build:geo  (node scripts/build-geo-translations.cjs)
     */
    function geo_localized_name(?string $name, string $dataset, ?string $locale = null): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en') {
            return $name;
        }

        static $maps = [];
        if (!isset($maps[$dataset])) {
            $maps[$dataset] = \Illuminate\Support\Facades\Cache::remember("geo_{$dataset}_map", 86400, function () use ($dataset) {
                $path = resource_path("data/{$dataset}-translations.json");

                return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
            });
        }

        $row = $maps[$dataset][mb_strtolower($name)] ?? null;

        return !empty($row[$locale]) ? $row[$locale] : $name;
    }
}

if (!function_exists('localized_city')) {
    function localized_city(?string $name, ?string $locale = null): string
    {
        return geo_localized_name($name, 'city', $locale);
    }
}

if (!function_exists('localized_country')) {
    function localized_country(?string $name, ?string $locale = null): string
    {
        return geo_localized_name($name, 'country', $locale);
    }
}

if (!function_exists('localized_address')) {
    /**
     * Full address with the city and country names localized.
     *
     * The API's fullAddress is a comma-joined string like
     * "Byuzand Street, —, Yerevan, Armenia". We tokenize it and translate each
     * token that geo-atlas recognizes as a city or country. Streets are left untouched.
     */
    function localized_address(array $prop, ?string $locale = null): string
    {
        $addr = trim((string) ($prop['fullAddress'] ?? ''));
        if ($addr === '') {
            return localized_city($prop['city'] ?? '', $locale);
        }

        $out = [];
        foreach (explode(',', $addr) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '—' || $part === '-') {
                continue;
            }
            $city = localized_city($part, $locale);
            if ($city !== $part) {
                $out[] = $city;
                continue;
            }
            $out[] = localized_country($part, $locale);
        }

        return implode(', ', $out);
    }
}
