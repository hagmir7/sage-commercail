<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class StockException extends Exception implements ShouldntReport
{
    //
}