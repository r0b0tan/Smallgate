<?php

namespace App\Services\Previews;

use RuntimeException;

/**
 * A thumbnail could not be taken. The message is a short reason code such as
 * "timeout" or "target_rejected" -- never a URL, a path or a token -- so it can
 * be logged as it is.
 */
class ThumbnailFailed extends RuntimeException {}
