<?php

namespace Sunnysideup\ArrayToCsvDownload\Api;

use Exception;
use SilverStripe\Assets\Filesystem;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ViewableData;
use Soundasleep\Html2Text;

class ArrayToCSV extends ViewableData
{
    /**
     * can the csv file be accessed directly or only via controller?
     *
     * @var bool
     */
    protected $hiddenFile = false;

    /**
     * "parent" controller.
     *
     * @var null|Controller
     */
    protected $controller;

    /**
     * name of the file - e.g. hello.csv OR hello/foo/bar.csv OR assets/uploads/tmp.csv.
     *
     * @var string
     */
    protected $fileName = '';

    /**
     * any array up to two levels deep.
     *
     * @var array
     */
    protected $array = [];

    /**
     * headers for CSV
     * formatted like this:
     * "Key" => "Label".
     *
     * @var array
     */
    protected $headers = [];

    /**
     * how many seconds before the file is stale?
     *
     * @var int
     */
    protected $maxAgeInSeconds = 86400;

    private static $hidden_download_dir = '_csv_downloads';

    private static $public_download_dir = 'csv-downloads';

    /**
     * internal.
     *
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
     *
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
     *
     * @param string $fileName        name of the file - e.g. hello.csv OR hello/foo/bar.csv OR assets/uploads/tmp.csv
     * @param array  $array           any array
     * @param int    $maxAgeInSeconds how long before the file is stale
     */
    public function __construct(string $fileName, array $array, ?int $maxAgeInSeconds = 86400)
    {
        $this->fileName = $fileName;
        $this->array = $array;
        $this->maxAgeInSeconds = $maxAgeInSeconds;
    }

    /**
     * ensures the file itself can not be downloaded directly.
     *
     * @param bool $bool
     */
    public function setHiddenFile(?bool $bool = true): self
    {
        $this->hiddenFile = $bool;

        return $this;
    }

    /**
     * e.g.
     * [
     *     "Key1" => "Label1"
     *     "Key2" => "Label2"
     *     "Key3" => "Label3"
     * ].
     */
    public function setHeaders(array $array): self
    {
        $this->headers = $array;

        return $this;
    }

    public function setHeadersFromClassName(string $className): self
    {
        $this->headers = Injector::inst()->get($className)->fieldLabels();

        return $this;
    }

    /**
     * @param SS_List $list any type of list - e.g. DataList
     */
    public function setList(SS_List $list): self
    {
        $this->array = $list->toNestedArray();

        return $list;
    }
    
    public function flatternArray(array $array, ?string $prefix = '') : array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = $prefix . (empty($prefix) ? '' : '.') . $key;
            if (is_array($value)) {
                $result = array_merge($result, $this->flatternArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
    
    public function createFile()
    {
        $path = $this->getFilePath();
        if (file_exists($path)) {
            unlink($path);
        }
        
        $this->array = $this->flatternArray($this->array);

        $file = fopen($path, 'w');
        if ($this->isAssoc()) {
            $row = $this->array[0];
            if (empty($this->headers)) {
                $keys = array_keys($row);
                $this->headers = array_combine($keys, $keys);
            }

            fputcsv($file, $this->headers);
        }

        foreach ($this->array as $row) {
            $count = count($row);
            $newRow = [];
            foreach ($this->headers as $key => $label) {
                try {
                    $newRow[$key] = Html2Text::convert(($row[$key] ?? ''), ['ignore_errors' => true]);
                } catch (Exception $exception) {
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
        $timeChange = 0;
        if (file_exists($path)) {
            $timeChange = filemtime($path);
        }
        if ($timeChange < $maxCacheAge) {
            $this->createFile();
        }
        if ($this->hiddenFile) {
            return HTTPRequest::send_file(file_get_contents($path), $this->fileName, 'text/csv');
        } else {
            return $this->controller->redirect($this->getFileUrl());
        }
    }

    protected function getFileUrl(): string
    {
        $path = $this->getFilePath();
        $remove = Controller::join_links(Director::baseFolder(), PUBLIC_DIR);
        $cleaned = str_replace($remove, '', $path);
        return Director::absoluteURL($cleaned);
    }

    protected function getFilePath(): string
    {
        if ($this->hiddenFile) {
            $hiddenDownloadDir = $this->Config()->get('hidden_download_dir') ?: '_csv_download_dir';
            $dir = Controller::join_links(Director::baseFolder(), $hiddenDownloadDir);
        } else {
            $publicDownloadDir = $this->Config()->get('public_download_dir') ?: 'csvs';
            $dir = Controller::join_links(ASSETS_PATH, $publicDownloadDir);
        }

        Filesystem::makeFolder($dir);
        $path = Controller::join_links($dir, $this->fileName);

        return (string) $path;
    }

    protected function isAssoc(): bool
    {
        reset($this->array);
        $row = $this->array[0] ?? [];
        if (empty($row)) {
            return false;
        }

        return array_keys($row) !== range(0, count($row) - 1);
    }
}
