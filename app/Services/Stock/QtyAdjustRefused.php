<?php

namespace App\Services\Stock;

use DomainException;

/** A hand qty overwrite that was not applied. The message is shown to the person as-is. */
class QtyAdjustRefused extends DomainException {}
