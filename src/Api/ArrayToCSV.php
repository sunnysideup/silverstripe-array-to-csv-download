<?php

namespace Sunnysideup\ArrayToCsvDownload\Api;


use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;

use SilverStripe\Control\HTTPRequest;

use SilverStripe\Control\Director;

use SilverStripe\Control\Controller;

use SilverStripe\Control\ContentNegotiator;

use SilverStripe\Core\Config\Config;

use SilverStripe\Core\Injector\Injector;

use SilverStripe\Assets\Filesystem;

use SilverStripe\View\ViewableData;
use Soundasleep\Html2Text;

use Exception;

use SilverStripe\ORM\SS_List;

class ArrayToCSV extends ViewableData
{

    private static $hidden_download_dir = '_csv_downloads';

    /**
     * can the csv file be accessed directly or only via controller?
     * @var bool
     */
    protected $hiddenFile = false;

    /**
     * "parent" controller
     * @var Controller|null
     */
    protected $controller = null;

    /**
     * name of the file - e.g. hello.csv OR hello/foo/bar.csv OR assets/uploads/tmp.csv
     * @var string
     */
    protected $fileName = '';

    /**
     * any array up to two levels deep
     * @var array
     */
    protected $array = [];

    /**
     * headers for CSV
     * formatted like this:
     * "Key" => "Label"
     * @var array
     */
    protected $headers = [];

    /**
     * how many seconds before the file is stale?
     * @var int
     */
    protected $maxAgeInSeconds = 86400;

    /**
     * internal
     * @var bool
     */
    private $infiniteLoopEscape = false;

    /**
     * typical array is like this:
     * ```php
     *     [
     *         [
     *             "Key1" => "Value1"
     *             "Key2" => "Value2"
     *             "Key3" => "Value3"
     *         ].

     *         [
     *             "Key1" => "Value1"
     *             "Key2" => "Value2"
     *             "Key3" => "Value3"
     *         ].
     *
     *         [
     *             "Key1" => "Value1"
     *             "Key2" => "Value2"
     *             "Key3" => "Value3"
     *         ].
     *     ]
     * ```
     * @param string  $fileName         name of the file - e.g. hello.csv OR hello/foo/bar.csv OR assets/uploads/tmp.csv
     * @param array   $array            any array
     * @param integer $maxAgeInSeconds  how long before the file is stale
     */
    public function __construct(string $fileName, array $array, ?int $maxAgeInSeconds = 86400)
    {
        $this->fileName = $fileName;
        $this->array = $array;
        $this->maxAgeInSeconds = $maxAgeInSeconds;
    }

    /**
     * ensures the file itself can not be downloaded directly
     * @param boolean $bool
     * @return self
     */
    public function setHiddenFile(?bool $bool = true) : self
    {
        $this->hiddenFile = true;
        return $this;
    }

    /**
     * e.g.
     * [
     *     "Key1" => "Label1"
     *     "Key2" => "Label2"
     *     "Key3" => "Label3"
     * ]
     * @param array $array
     * @return self
     */
    public function setHeaders(array $array) : self
    {
        $this->headers = $array;
        return $this;
    }

    /**
     *
     * @param  string $className
     * @return self
     */
    public function setHeadersFromClassName(string $className) : self
    {
        $this->headers = Injector::inst()->get($className)->fieldLabels();
        return $this;
    }

    /**
     *
     * @param  SS_List $list any type of list - e.g. DataList
     * @return self
     */
    public function setList(SS_List $list) : self
    {
        $this->array = $list->toNestedArray();
        return $list;
    }

    public function createFile()
    {
        $path = $this->getFilePath();
        if(file_exists($path)) {
            unlink($path);
        }
        $file = fopen($path, 'w');
        if($this->isAssoc()) {
            $row = $this->array[0];
            $this->rowCountCheck = count($row);
            if(empty($this->headers)) {
                $this->headers = array_keys($row);
            }
            fputcsv($file, $this->headers);
        }
        foreach ($this->array as $row) {
            $count = count($row);
            $newRow = [];
            foreach($headers as $key) {
                try {
                    $newRow[$key] = Html2Text::convert(($row[$key] ?? ''), ['ignore_errors' => true,]);
                } catch (Exception $e) {
                    $newRow[$key] = 'error';
                }
            }
            fputcsv($file, $newRow);
        }
        fclose($file);
    }

    public function redirectToFile(?Controller $controller = null)
    {
        $this->controller = $controller ?: Controller::curr();
        $maxCacheAge = strtotime('Now') - ($this->maxAgeInSeconds);
        $path = $this->getFilePath();
        if(file_exists($path)) {
            $timeChange = filemtime($path);
            if($timeChange > $maxCacheAge) {
                if($this->hiddenFile) {
                    $this->controller->setResponse(HTTPRequest::send_file(file_get_contents($path), $this->fileName, 'text/csv'));
                } else {
                    return $this->controller->redirect('/'.$this->fileName);
                }
            }
        }
        $this->createFile();
        if($this->infiniteLoopEscape === false) {
            $this->infiniteLoopEscape = true;
            return $this->redirectToFile($controller);
        }
        return $this->controller->redirect('/'.$this->fileName);
    }

    protected function getFilePath() : string
    {
        if($this->hiddenFile) {
            $hiddenDownloadDir = $this->Config()->get('hidden_download_dir') ?: '_csv_download_dir';
            $dir = Controller::join_links(Director::baseFolder(), $hiddenDownloadDir);
        } else {
            $dir = Controller::join_links(Director::baseFolder(), PUBLIC_DIR);
        }
        Filesystem::makeFolder($dir);
        $path = Controller::join_links($dir, $this->fileName);

        return (string) $path;
    }

    protected function isAssoc() : bool
    {
        reset($this->array);
        $row = $this->array[0] ?? [];
        if (empty($row)) {
            return false;
        }
        return array_keys($row) !== range(0, count($row) - 1);
    }


}
