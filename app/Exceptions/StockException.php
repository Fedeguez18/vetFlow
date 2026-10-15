<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A stock operation that can't be applied (invalid quantity, missing lot, not enough units).
 */
class StockException extends RuntimeException {}
