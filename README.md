# PHPEasyDeploy

Everytime you run this class, it will check for all your local files and folders and it will compare with the specified FTP, in order to see if there are some changes. If there are, it will automatically deploy the changes into your FTP (add file(s), edit file(s) or delete file(s)) so the environments will be exactly equal all the time.

## Options

Just include the file, set the options and you can easily deploy your local files to FTP.

* __NotAllowedRoutes__: Array with the path of file(s) you don't want to be deployed
* __DirToCompareWithFTP__: Local files path will get compared with the current FTP and find the differences
* __FTPDirectory__: The FTP Path to get compared with local files
* __FTPHost__: FTP Host (*example.com*)
* __FTPUser__: FTP User
* __FTPPassword__: FTP Password
* __ShowDebug__: It will show a debug history using the following method: `showDebugResults()`


## Using the Class

```php
<?php

/*
* Sometimes it takes a long time, we recommend to Set Time Limit to 0
*/  

set_time_limit(0);
ini_set('max_execution_time', 0);

/*
* Include the class
*/  

require_once('path/to/class.phpeasydeploy.php');  

$deploy = new PHPEasyDeploy;

/*
* Set FTP Params to connect
*/  

$deploy->FTPHost = "yourhost.com";
$deploy->FTPUser = "yourUser";
$deploy->FTPPassword = "yourPassword";

/*
* Set FTP Directory where you are going to compare it with your local files
*/  

$deploy->FTPDirectory = "public_html/folder";

/*
* Show the debug results after its ending
*/

$deploy->ShowDebug = true;

/*
* Run the Class
*/

$deploy->equalEnvironments();

?>
```

## License

Copyright (c) 2017 Iván Prat (https://github.com/IvanPrat)

Licensed under the MIT License (http://www.opensource.org/licenses/mit-license.php)
