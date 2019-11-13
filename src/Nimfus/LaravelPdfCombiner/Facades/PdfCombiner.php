<?php
namespace Nimfus\LaravelPdfMerge\Facades;

use Illuminate\Support\Facades\Facade;

class PdfCombiner extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'pdf-merger';
    }
} 