<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\TeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertyCacheController extends Controller
{
    public function forget(Request $request)
    {
        session()->save();
        $slug = $request->input('slug', '');
        if (is_string($slug) && strlen($slug) <= 200 && strlen($slug) > 0) {
            // Detail payload is cached per locale (localized Title/Description),
            // and the client doesn't know which — clear every supported language.
            foreach (['en', 'ru', 'hy'] as $locale) {
                Cache::forget(TeCache::prop($slug, $locale));
            }
        }
        return response()->json(['ok' => true]);
    }
}
