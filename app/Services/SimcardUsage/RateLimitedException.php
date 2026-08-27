<?php

namespace App\Services\SimcardUsage;

use RuntimeException;

/**
 * The telco usage API refused the request for rate reasons (HTTP 429). The
 * sync layer logs it and stops hitting that provider until the next cron run
 * instead of hammering through the remaining chunks.
 */
class RateLimitedException extends RuntimeException {}
