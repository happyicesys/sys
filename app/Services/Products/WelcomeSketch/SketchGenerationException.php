<?php

namespace App\Services\Products\WelcomeSketch;

use RuntimeException;

/** The provider refused or failed; the message is kept on the sketch row for Product → Edit. */
class SketchGenerationException extends RuntimeException {}
