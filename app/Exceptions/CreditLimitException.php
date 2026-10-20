<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A remito that would take the producer's balance above their credit limit and was not confirmed.
 */
class CreditLimitException extends RuntimeException {}
