<?php

namespace App\Services\ApkMedia;

use RuntimeException;

/** A banner upload that cannot be fitted to the machine; the message is shown to the uploader. */
class MediaRejected extends RuntimeException {}
