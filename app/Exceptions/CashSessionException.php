<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A caja that can't be opened or closed (another one is open, invalid amount, already closed).
 */
class CashSessionException extends RuntimeException {}
