<?php

namespace App\Exceptions\Accurate;

class AccurateApiException extends AccurateException
{
    public function __construct(string $message, protected mixed $rawData = null)
    {
        parent::__construct($message);
    }

    public function rawData(): mixed
    {
        return $this->rawData;
    }
}
