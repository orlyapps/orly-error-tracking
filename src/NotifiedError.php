<?php

namespace Orlyapps\OrlyErrorTracking;

use RuntimeException;

/**
 * Carrier for notifyError(): the stack trace shows where it was called from, the
 * class name sent to Orly is the name given by the application.
 */
class NotifiedError extends RuntimeException {}
