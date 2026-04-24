<?php

namespace App\Exceptions;

use Exception;

class RootImmunityException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'ROOT_IMMUNITY_VIOLATION',
            'message' => 'The root administrator account is immune to master-level modifications or deletion.'
        ], 403);
    }
}
