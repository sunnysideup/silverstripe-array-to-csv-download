# tl;dr

```php
$array1 = [
    'Hello' => 'Aaa',
];
$array2 = [
    'Aaa',
    'Bbb',
    'Ccc',
];
// create the CSV file - hidden or in public dir ...
(new ArrayToCSV('mydata.csv', $array1, $maxAge = 86400))->setHiddenFile(true)->createFile();
(new ArrayToCSV('assets/csv-downloads/myotherdata.csv', $array2, $maxAge = 86400))->setHiddenFile(false)->createFile();

// redirect to download
(new ArrayToCSV('mydata.csv', $array1))->setHiddenFile(false)->redirectToFile($currentController);
(new ArrayToCSV('private.csv', $array2))->setHiddenFile(true)->redirectToFile($currentController);
