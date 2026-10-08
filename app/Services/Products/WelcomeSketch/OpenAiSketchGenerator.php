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
Redraw the product in the FIRST image as one cute hand-drawn sticker illustration, in exactly the
same art style as the other images (soft coloured-pencil texture, clean dark outline, warm flat
colours, gentle shading). Keep the product recognisable: same shape, colours and packaging, as it
looks when held. Draw only the product, centred and upright, filling most of the canvas. No
background, no shadow, no extra objects, no added text beyond what is printed on the product.
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
                'prompt' => self::PROMPT."\nProduct: {$productName}.",
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
