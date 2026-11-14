<?php

namespace App\Services\SmartFreezer\Claude;

use App\Models\ZijiaSkuApplication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Imagick;
use Throwable;

/**
 * Every product the AI is trained on has an approved application with its training photos (Brian, 2026-11-14: "each
 * SKU will have their training images … utilise the different angle or packaging"): the pack shot and the model photos
 * from vms4 or AI Training — front, back, sides and both ends (a cone's round top is what the camera sees lying in a
 * basket). Claude gets them as ONE strip per product, `reference_photos.tile_px` square tiles side by side, in place of
 * the single product photo: ~180 px tiles × 8 = ~270 image tokens, about S$0.001 a product a session.
 *
 * The originals are 1.5–2 MB each on Spaces, so a strip is built once and cached by the exact photo list
 * (`cache_days`); a new application (new photos) gives a new key. A product with no approved application, or whose
 * photos cannot be read, keeps its single product photo.
 */
final class ProductViews
{
    /**
     * @param  list<int>  $productIds
     * @return array<int, array{jpeg: string, views: int}> product id => strip
     */
    public static function strips(array $productIds): array
    {
        $config = (array) config('smart_freezer.claude_recognition.reference_photos');
        if (! (bool) ($config['enabled'] ?? false) || $productIds === []) {
            return [];
        }
        $applications = ZijiaSkuApplication::query()->whereIn('product_id', $productIds)
            ->where('status', ZijiaSkuApplication::STATUS_APPROVED)->orderByDesc('id')->get()->unique('product_id');

        $strips = [];
        foreach ($applications as $application) {
            $urls = self::urls($application, max(1, (int) ($config['max_views'] ?? 8)));
            if ($urls === []) {
                continue;
            }
            $tile = max(64, (int) ($config['tile_px'] ?? 180));
            $key = 'claude-freezer-views:'.$tile.':'.sha1(implode("\n", $urls));
            $strip = Cache::get($key);
            if (! is_array($strip)) {
                $strip = self::build($urls, $tile);
                if ($strip !== null) {
                    Cache::put($key, $strip, now()->addDays(max(1, (int) ($config['cache_days'] ?? 30))));
                }
            }
            if (is_array($strip) && isset($strip['jpeg'])) {
                $strips[(int) $application->product_id] = ['jpeg' => (string) base64_decode($strip['jpeg']), 'views' => (int) $strip['views']];
            }
        }

        return $strips;
    }

    /** @return list<string> the pack shot, then the model photos, each once */
    public static function urls(ZijiaSkuApplication $application, int $max): array
    {
        $urls = [$application->package_image_url];
        foreach (['high', 'horizontal', 'low'] as $angle) {
            foreach ((array) (((array) $application->model_pics)[$angle] ?? []) as $url) {
                $urls[] = $url;
            }
        }
        $urls = array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && str_starts_with($u, 'https://'))));

        return array_slice($urls, 0, $max);
    }

    /**
     * @param  list<string>  $urls
     * @return array{jpeg: string, views: int}|null base64 jpeg, for the cache
     */
    private static function build(array $urls, int $tile): ?array
    {
        $strip = new Imagick;
        try {
            $views = 0;
            foreach ($urls as $url) {
                try {
                    $response = Http::timeout(20)->get($url);
                    if (! $response->successful() || $response->body() === '') {
                        continue;
                    }
                    $image = new Imagick;
                    $image->readImageBlob($response->body());
                    $image->setIteratorIndex(0);
                    $image->setImageBackgroundColor('white');
                    $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                    $image->thumbnailImage($tile, $tile, true);
                    $image->setImageBackgroundColor('white');
                    $image->extentImage($tile, $tile, -intdiv($tile - $image->getImageWidth(), 2), -intdiv($tile - $image->getImageHeight(), 2));
                    $strip->addImage($image);
                    $views++;
                } catch (Throwable) {
                    continue;
                }
            }
            if ($views === 0) {
                return null;
            }
            $strip->resetIterator();
            $joined = $strip->appendImages(false);
            $joined->setImageFormat('jpeg');
            $joined->setImageCompressionQuality(82);

            return ['jpeg' => base64_encode($joined->getImageBlob()), 'views' => $views];
        } catch (Throwable $e) {
            report($e);

            return null;
        } finally {
            $strip->clear();
        }
    }
}
