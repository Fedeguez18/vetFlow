<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment that can't be registered (invalid amount, more than the producer owes).
 */
class PaymentException extends RuntimeException {}
