# tl;dr

use SilverStripe\ORM\DataObject;

```php

class MyPageController extends PageController
{

    private static $allowed_actions = [
        'csv' => true,
        'csvfancy' => true,
    ];

    public function csv($httpRequest)
    {
        // redirect to download
        (ArrayToCSV::create('mydata.csv', $this->getArray(), 86400))
            ->redirectToFile($this);

    }

    public function csvfancy($httpRequest)
    {
        // here are just some other example uses (you can mix and match here)
        $obj = MyDataObject::get()->first();
        # special options
        (new ArrayToCSV('private.csv', $this->getArray()))
            ->setHiddenFile(true)                           // dont provide public access to the file
            ->setHeaders($obj->getFieldLabels())            // set the CSV headers
            ->setList(MyDataObject::get())                  // provide an SS_List 
            ->setHeadersFromClassName(MyDataObject::class)  // use the headers based on a class
            ->redirectToFile($this);                        // you always want to finish with this one.
    }

    protected function getArray() : array
    {
        return = [
            'Hello' => 'Aaa',
        ];        
    }

    protected function warmCache()
    {
        // create the CSV file - hidden or in public dir ...
        (new ArrayToCSV('mydata.csv', $this->getArray(), $maxAge = 86400))
            ->createFile();
    }
}
```
