<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A category or supplier change that would break existing records (e.g. deleting one in use).
 */
class CatalogException extends RuntimeException {}
