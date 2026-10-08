<?php

namespace App\Services\Products\WelcomeSketch;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI image edit (`POST /images/edits`): the product photo first, then approved sketches as the
 * style to copy, asking for one transparent-background drawing. Key: services.openai.api_key
 * (OPENAI_API_KEY); model/quality: smart_freezer.welcome_sketch.
 */
class OpenAiSketchGenerator implements SketchGenerator
{
    public const PROMPT = <<<'TXT'
Redraw the food in the FIRST image as one cute hand-drawn sticker illustration, in exactly the
same art style as the other images (soft coloured-pencil texture, clean dark outline, warm flat
colours, gentle shading). Keep it recognisable: same shape and colours. Draw it centred and
upright, filling most of the canvas. No background, no shadow, no extra objects.
Draw the food the way it is eaten, NOT its retail packaging:
- An ice cream bar, stick or ice pop: only the unwrapped bar on its stick. If the photo also shows
  its wrapper, packet or box beside it, leave that out completely.
- A cone: the cone with its printed paper sleeve, as it is held — no outer wrapper or lid.
- A cake or pastry: only the cake (with its baking paper if it sits in one), no box or bag.
- Only when the food is eaten straight from its container — a cup or tub, a tray or box of fruit —
  draw that container, as it looks when opened or held.
Text: copy ONLY words clearly printed on what you draw, spelled exactly. A plain ice bar, loose
fruit or a bare cake gets NO text at all. Never invent a label, sticker, wrapper, bag or box, and
never write the product's name onto it.
Transparent background.
TXT;

    public function isConfigured(): bool
    {
        return trim((string) config('services.openai.api_key')) !== '';
    }

    public function model(): string
    {
        return (string) config('smart_freezer.welcome_sketch.model', 'gpt-image-1');
    }

    public function generate(string $photo, string $productName, array $styleReferences): string
    {
        $request = Http::withToken((string) config('services.openai.api_key'))
            ->timeout((int) config('smart_freezer.welcome_sketch.timeout_seconds', 150))
            ->attach('image[]', $photo, 'product.png');
        foreach (array_values($styleReferences) as $i => $reference) {
            $request = $request->attach('image[]', $reference, "style-{$i}.webp");
        }

        try {
            $response = $request->post(rtrim((string) config('services.openai.base_uri', 'https://api.openai.com/v1'), '/').'/images/edits', [
                'model' => $this->model(),
                'prompt' => self::PROMPT."\nWhat the product is (for reference only — do not write this on it): {$productName}.",
                'background' => 'transparent',
                'output_format' => 'png',
                'size' => '1024x1024',
                'quality' => (string) config('smart_freezer.welcome_sketch.quality', 'medium'),
                'n' => 1,
            ]);
        } catch (ConnectionException $e) {
            throw new SketchGenerationException('Image service unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $message = $response->json('error.message') ?? mb_substr($response->body(), 0, 300);
            throw new SketchGenerationException("Image service refused (HTTP {$response->status()}): {$message}");
        }
        $b64 = $response->json('data.0.b64_json');
        $bytes = is_string($b64) ? base64_decode($b64, true) : false;
        if ($bytes === false || $bytes === '') {
            throw new SketchGenerationException('Image service answered without an image.');
        }

        return $bytes;
    }
}
