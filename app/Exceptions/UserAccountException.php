<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A change to a user account that isn't allowed (e.g. an administrator locking themselves out).
 */
class UserAccountException extends RuntimeException {}
