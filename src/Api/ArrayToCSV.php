<?php

namespace Sunnysideup\ArrayToCsvDownload\Api;


use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;

use SilverStripe\Control\Director;

use SilverStripe\Control\Controller;

use SilverStripe\Control\ContentNegotiator;

use SilverStripe\Core\Config\Config;
use Soundasleep\Html2Text;



class ArrayToCSV extends ViewableObject
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
            fputcsv($file, array_keys(reset($this->array)));
        }
        foreach ($this->array as $row) {
            foreach($row as $key => $value) {
                $row[$key] = Html2Text::convert($value);
            }
            fputcsv($file, $row);
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
                    return $this->controller->redirect($this->fileName);
                }
            }
        }
        $this->createFile();
        if($this->infiniteLoopEscape === false) {
            $this->infiniteLoopEscape = true;
            $this->redirectToFile($controller);
        }
    }

    protected function getFilePath() : string
    {
        if($this->hiddenFile) {
            $hiddenDownloadDir = $this->Config()->get('hidden_download_dir') ?: '_csv_download_dir';
            $dir = Controller::join_links(Director::baseFolder(), $hiddenDownloadDir);
        } else {
            $dir = Controller::join_links(Director::baseFolder(), PUBLIC_DIR);
        }
        Filesystem::makeFolder($path);
        $path = Controller::join_links($dir, $this->fileName);

        return (string) $path;
    }

    protected function isAssoc() : bool
    {
        if (array() === $this->array) return false;
        return array_keys($this->array) !== range(0, count($this->array) - 1);
    }


}
