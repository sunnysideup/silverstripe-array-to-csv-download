<?php

namespace Sunnysideup\ArrayToCsvDownload\Api;


use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;

use SilverStripe\Control\HTTPRequest;

use SilverStripe\Control\Director;

use SilverStripe\Control\Controller;

use SilverStripe\Control\ContentNegotiator;

use SilverStripe\Core\Config\Config;

use SilverStripe\Assets\Filesystem;

use SilverStripe\View\ViewableData;
use Soundasleep\Html2Text;

use Exception;

class ArrayToCSV extends ViewableData
{

    private static $hidden_download_dir = '_csv_downloads';

    protected $hiddenFile = false;

    protected $controller = null;

    protected $fileName = '';

    protected $array = [];

    protected $maxAgeInSeconds = 86400;

    private $infiniteLoopEscape = false;

    public function __construct(string $fileName, array $array, ?int $maxAgeInSeconds = 86400)
    {
        $this->fileName = $fileName;
        $this->array = $array;
        $this->maxAgeInSeconds = $maxAgeInSeconds;
    }

    public function setHiddenFile(?bool $bool = true)
    {
        $this->hiddenFile = true;
        return $this;
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
            $headers = array_keys($row);
            fputcsv($file, $headers);
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
        if (empty($this->array)) {
            return false;
        }
        reset($this->array);
        $row = $this->array[0];
        return array_keys($row) !== range(0, count($row) - 1);
    }


}
