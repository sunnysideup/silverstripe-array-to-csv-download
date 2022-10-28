<?php

namespace Sunnysideup\ArrayToCsvDownload\Control;
use Sunnysideup\ArrayToCsvDownload\Api\ArrayToCSV;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;

use SilverStripe\Core\Config\Config;

class HiddenDownloader extends Controller
{

    private static $allowed_actions = [
        'download' => 'ADMIN',
    ];



    public function download(HTTPRequest $request)
    {
        $fileName = $this->request->param('ID').'.csv';
        $hiddenDownloadDir = Config::inst()->get(ArrayToCSV::class, 'hidden_download_dir') ?: '_csv_download_dir';
        $path = Controller::join_links(Director::baseFolder(), $hiddenDownloadDir, $fileName);
        return HTTPRequest::send_file(file_get_contents($path), $fileName, 'text/csv');
    }
}
