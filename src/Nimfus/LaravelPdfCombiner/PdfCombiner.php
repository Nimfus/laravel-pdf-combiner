<?php

namespace Nimfus\LaravelPdfMerge;

use Exception;
use LaravelPpfCombiner\Exceptions\PdfOutputException;
use TCPDI;

require_once('tcpdf/tcpdf.php');
require_once('tcpdf/tcpdi.php');

class PdfCombiner
{
    const WIDTH = 'w';
    const HEIGHT = 'h';

    private $files = null;    //['form.pdf']  ["1,2,4, 5-19"]
    private $tcpdi;

    public function __construct()
    {
        $this->tcpdi = new TCPDI();
        $this->tcpdi->setPrintHeader(false);
        $this->tcpdi->setPrintFooter(false);

        return $this;
    }

    /**
     * Add a PDF for inclusion in the merge with a valid file path. Pages should be formatted: 1,3,6, 12-16.
     * @param $filepath
     * @param $pages
     * @param $orientation
     * @return PdfCombiner
     * @throws Exception
     */
    public function addPDF($filepath, $pages = 'all', $orientation = null)
    {
        if (file_exists($filepath) && strtolower($pages) !== 'all') {
            $pages = $this->rewritePages($pages);

            $this->files[] = [$filepath, $pages, $orientation];
        } else {
            throw new Exception("Could not locate PDF on '$filepath'");
        }

        return $this;
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param $orientation
     * @param array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     * @param bool $duplex merge with
     * @throws Exception
     * @array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     */
    private function doMerge($orientation = null, $meta = [], $duplex = false)
    {
        if (!isset($this->files) || !is_array($this->files)) {
            throw new Exception("No PDFs to merge.");
        }

        // setting the meta tags
        if (!empty($meta)) {
            $this->setMeta($meta);
        }

        // merger operations
        foreach ($this->files as $file) {
            $filename = $file[0];
            $filePages = $file[1];
            $fileOrientation = (!is_null($file[2])) ? $file[2] : $orientation;

            $count = $this->tcpdi->setSourceFile($filename);

            //add the pages
            if ($filePages === 'all') {
                for ($i = 1; $i <= $count; $i++) {
                    $template = $this->tcpdi->importPage($i);
                    $size = $this->tcpdi->getTemplateSize($template);
                    $this->addPage($template, $size, $fileOrientation, $orientation);
                }
            } else {
                foreach ($filePages as $page) {
                    if (!$template = $this->tcpdi->importPage($page)) {
                        throw new Exception("Could not load page '$page' in PDF '$filename'. Check that the page exists.");
                    }
                    $size = $this->tcpdi->getTemplateSize($template);
                    $this->addPage($template, $size, $fileOrientation, $orientation);
                }
            }
            if ($duplex && $this->tcpdi->PageNo() % 2) {
                $this->tcpdi->AddPage($fileOrientation, [$size[self::WIDTH], $size[self::HEIGHT]]);
            }
        }
    }

    /**
     * @param $template
     * @param $size
     * @param $fileOrientation
     * @param $orientation
     */
    private function addPage($template, $size, $fileOrientation, $orientation)
    {
        if (!$orientation) $fileOrientation = $size[self::WIDTH] < $size[self::HEIGHT] ? 'P' : 'L';

        $this->tcpdi->AddPage($fileOrientation, [$size[self::WIDTH], $size[self::HEIGHT]]);
        $this->tcpdi->useTemplate($template);
    }

    /**
     * Merges your provided PDFs and outputs to specified location.
     * @param string $orientation
     * @param array $meta
     *
     * @return void
     *
     * @throws Exception if there are no PDFs to merge
     */
    public function merge($orientation = null, $meta = [])
    {
        $this->doMerge($orientation, $meta, false);
    }

    /**
     * Merges your provided PDFs and adds blank pages between documents as needed to allow duplex printing
     * @param string $orientation
     * @param array $meta
     *
     * @return void|bool|string
     *
     * @throws Exception
     */
    public function duplexMerge($orientation = null, $meta = [])
    {
        $this->doMerge($orientation, $meta, true);
    }

    public function save($outputPath = 'new_file.pdf', $outputMode = 'file')
    {
        $mode = $this->switchMode($outputMode);

        if ($mode === 'S') {
            return $this->tcpdi->Output($outputPath, 'S');
        } elseif ($this->tcpdi->Output($outputPath, $mode) === '') {
            return true;
        }

        throw new PdfOutputException("Error outputting PDF to '$outputMode'.");
    }

    /**
     * FPDI uses single characters for specifying the output location. Change our more descriptive string into proper format.
     * @param $mode
     * @return string
     */
    private function switchMode($mode)
    {
        switch (strtolower($mode)) {
            case 'download':
                return 'D';
                break;
            case 'file':
                return 'F';
                break;
            case 'string':
                return 'S';
                break;
            case 'browser':
            default:
                return 'I';
                break;
        }
    }

    /**
     * Takes our provided pages in the form of 1,3,4,16-50 and creates an array of all pages
     * @param $pages
     * @return array
     * @throws Exception
     */
    private function rewritePages($pages)
    {
        $pages = str_replace(' ', '', $pages);
        $part = explode(',', $pages);
        $newPages = [];

        //parse hyphens
        foreach ($part as $hyphenString) {
            $hyphenSegments = explode('-', $hyphenString);

            if (count($hyphenSegments) === 2) {
                $firstPage = $hyphenSegments[0]; //start page
                $lastPage = $hyphenSegments[1]; //end page

                if ($firstPage > $lastPage) {
                    throw new Exception("Starting page, '$firstPage' is greater than ending page '$lastPage'.");
                }

                //add middle pages
                while ($firstPage <= $lastPage) {
                    $newPages[] = (int)$firstPage;
                    $firstPage++;
                }
            } else {
                $newPages[] = (int)$hyphenSegments[0];
            }
        }

        return $newPages;
    }

    /**
     * Set your meta data in merged pdf
     * @param array $meta [title => $title, author => $author, subject => $subject, keywords => $keywords, creator => $creator]
     * @return void
     */
    protected function setMeta(array $meta)
    {
        foreach ($meta as $key => $arg) {
            $methodName = 'set' . ucfirst($key);
            if (method_exists($this->tcpdi, $methodName)) {
                $this->tcpdi->$methodName($arg);
            }
        }
    }

}
