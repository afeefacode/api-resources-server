<?php

namespace Afeefa\ApiResources\Exception\Exceptions;

use Afeefa\ApiResources\Exception\Exception;

class ApiException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Api Exception');

        $this->message = [
            'message' => $message,
            'request' => json_decode(file_get_contents('php://input'), true)
        ];
    }
}
