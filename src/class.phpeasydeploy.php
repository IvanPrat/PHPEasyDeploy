<?php
/**
 * Copyright (c) 2017 Iván Prat. All Right Reserved.
 *
 * @name      phpeasydeploy
 *
 * @author    Iván <ivanprat92@gmail.com>
 *
 * @copyright 2017 Iván Prat
 *
 * @url       https://github.com/IvanPrat/PHPEasyDeploy
 *
 * @license   MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

class PHPEasyDeploy
{
    /**
     * The PHPEasyDeploy Version number.
     * @var string
     */

    public $Version = '1.0.0';

    /**
     * Name of the JSON file with stored file timing
     * @var string
     */
    public $JSONStoredStatusPath = ".to-update-ftp.json";

    /**
     * Routes not allowed to be deployed when you run the solution
     * @var array
     */
    public $NotAllowedRoutes = array($JSONStoredStatusPath);

    /**
     * Dir Path that will be compared with the Current FTP
     * @var string
     */
    public $DirToCompareWithFTP = '../';

    /**
     * Dir FTP Path that will be compared with the Current Files
     * @var string
     */
    public $FTPDirectory = '';

    /**
     * FTP Host where you're going to deploy the code
     * @var string
     */
    public $FTPHost = '';

    /**
     * FTP User
     * @var string
     */
    public $FTPUser = '';

    /**
     * FTP Password
     * @var string
     */
    public $FTPPassword = '';

    /**
     * If it's true, it will output the Debug Log
     * @var boolean
     */
    public $ShowDebug = true;

    /**
     * FTP with the current opened connection
     * @var array
     */
    protected $FTPConnection = array();

    /**
     * FTP Files to Update once compared
     * @var array
     */
    protected $FilesToUpdate = array();

    /**
     * FTP Files to Remove once compared
     * @var array
     */
    protected $FilesToRemove = array();

    /**
     * FTP Files to Add once compared
     * @var array
     */
    protected $FilesToAdd = array();

    /**
     * All Items compared
     * @var array
     */
    private $AllItems = array();

    /**
     * FTP Files
     * @var array
     */
    private $FilesOutput = array();

    /**
     * It Opens an FTP Connection with the objective of compare your local files
     *
     * @param string $ftp_host FTP Host
     * @param string $ftp_user FTP User
     * @param string $ftp_user FTP Password
     * @access  private
     * @return  boolean
     */
    private function openFTPConnection($ftp_host, $ftp_user, $ftp_password)
    {
      if($ftp_host && $ftp_user && $ftp_password)
      {
        try {
  				$conn_test = ftp_connect($ftp_host);

  				if (false === $conn_test) {
  					throw new Exception('Unable to connect');
  				}

  				$loggedIn = ftp_login($conn_test, $ftp_user, $ftp_password);

  				if(false === $loggedIn) {
  					throw new Exception('Unable to log in');
  				}

  				ftp_close($conn_test);
  			}
        catch (Exception $e)
        {
  				echo "Failure: " . $e->getMessage();

          return false;
  			}

  			// We open a FTP session

  			$this->FTPConnection = ftp_connect($ftp_host);

  			$ftp_connection = ftp_login($this->FTPConnection, $ftp_user, $ftp_password);

  			// Set Passive Mode
  			ftp_pasv($this->FTPConnection, true);
      }

      return true;
    }

    /**
     * It checks if it is an FTP Directory
     *
     * @param array $ftp_con FTP Connection
     * @param string $dir Directory to be checked
     * @access  private
     * @return  boolean
     */
    private function checkFTPIsDir($ftp_con, $dir)
  	{
      $original_directory = ftp_pwd($ftp_con);

      if(@ftp_chdir($ftp_con, $dir))
      {
        ftp_chdir($ftp_con, $original_directory);
        return true;
      }

      return false;
  	}

    /**
     * It creates a new FTP Path
     *
     * @param array $ftp_con FTP Connection
     * @param string $dir Path to be created
     * @access  private
     * @return  boolean
     */
    private function makeFTPPath($ftp_con, $path)
  	{
  	    $dir = pathinfo($path , PATHINFO_DIRNAME);

  	    if($this->checkFTPIsDir($ftp_con, $dir))
  	    {
	        return true;
  	    }
  	    else
  	    {
	        if($this->makeFTPPath($ftp_con, $dir))
	        {
            if(ftp_mkdir($ftp_con, $dir))
            {
              return true;
            }
	        }
  	    }

  	    return false;
  	}

    /**
     * Check if inside array there's an element starting with...
     *
     * @param string $path String to be checked (needle)
     * @param array $array Array (handle)
     * @access private
     * @return  boolean
     */
    private function checkInArrayBegginingWith($path, $array)
  	{
  		foreach($array as $k => $begin)
  			if(strncmp($path, $begin, strlen($begin)) == 0)
  			  return true;

  		return false;
  	}

    /**
     * Check for the path and return it as an array
     *
     * @param string $path String to be checked (needle)
     * @param array $array Array (handle)
     * @access private
     * @return array
     */
    private function pathToArray($path , $separator = '/')
  	{
  		if(($pos = strpos($path, $separator)) === false)
  		{
  			return array($path);
  		}

  		return array(substr($path, 0, $pos) => $this->pathToArray(substr($path, $pos + 1)));
  	}

    /**
     * Check if JSON with logged timing exists
     *
     * @param string $path String to be checked
     * @access public
     * @return boolean
     */
    public function checkIfJSONFileExists($path)
    {
      $return_path = $path;

      if(!file_exists($path))
        $this->createJSONfile($path);

      return $return_path;
    }

    /**
     * Decode the JSON and parse the info to see if there are files ready to update/delete
     *
     * @param string $path String to be checked
     * @access private
     * @return boolean
     */
    private function dumpDataFromJSON($path)
    {
      $json_last_updates_code          = file_get_contents($path, FILE_USE_INCLUDE_PATH);
      $json_decoded_last_updates_code  = json_decode($json_last_updates_code);

      // Json decoded Object
      foreach($json_decoded_last_updates_code as $route => $time)
      {
        // We add every item as an array value, it will be used when checking if need to add
        $this->AllItems[$route] = $time;

        if(file_exists($route))
        {
          // If file exists, we check if has been updated
          if(filemtime($route) != $time)
          {
          	$this->FilesToUpdate[] = $route;
          }
        }
        else
        {
          // Seems the file has been removed
          $this->FilesToRemove[] = $route;
        }
      }

      return true;
    }

    /**
     * (void)
     * Create new JSON initial file (usually it means there are no JSON file)
     *
     * @param string $path String to be checked
     * @access public
     * @return
     */
    public function createJSONfile($path)
    {
      $fp = fopen($path, "wb");

      fwrite($fp, "");
      fclose($fp);

      // Update the JSON with empty (we still have to upload it all!)
      $this->updateJSONPathContent($this->JSONStoredStatusPath, json_encode(array()));
    }

    /**
     * (void)
     * Update JSON with new values
     *
     * @param string $file_path_ftp File Path for JSON
     * @param array $json_files Array with Encoded JSON
     * @access public
     * @return
     */

    public function updateJSONPathContent($file_path_ftp, $json_files = array())
    {
      return file_put_contents($file_path_ftp, $json_files);
    }

    /**
     * Find the main differences between local files and FTP
     *
     * @access public
     * @return boolean
     */
    public function findDifferences()
    {
      $results = array();

      if(is_dir($this->DirToCompareWithFTP))
      {
      	// Let's iterate the folder in order to find differences
      	$iterator = new RecursiveDirectoryIterator($this->DirToCompareWithFTP);

      	foreach (new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST) as $file)
      	{
      		if ($file->isFile())
      		{
      			$thispath = 		str_replace('\\', '/', $file);
      			$thisfile = 		utf8_encode($file->getFilename());

      			$def = 				$thisfile;
      			$results = 			array_merge_recursive($results, $this->pathToArray($thispath));

      			$path_this_file = 	$thispath . ($def != $thisfile ? '/' . $thisfile : '');
      			$this_dir = dirname($path_this_file);

    				// If this route is not a disallowed route
    				if(!in_array($path_this_file, $this->NotAllowedRoutes) && !$this->checkInArrayBegginingWith($path_this_file, $this->NotAllowedRoutes))
    				{
    					$this->FilesOutput[$path_this_file] = filemtime($path_this_file);

    					// If file doesn't exists, then we add to new files
    					if(!isset($this->AllItems[$path_this_file]))
    					{
    						$this->FilesToAdd[] = $path_this_file;
    					}
    				}
      		}
      	}

        return true;
      }

      return false;
    }

    /**
     * (void)
     * Simply shows the debug info according to the changes
     *
     * @access public
     * @return
     */
    public function showDebugResults()
    {
      printf("<h1>Added Files To FTP</h1>");

      foreach($this->FilesToAdd as $file)
      {
        printf($file . '<br/>');
      }

      printf("<h1>Updated Files To FTP</h1>");

      foreach($this->FilesToUpdate as $file)
      {
        printf($file . '<br/>');
      }

      printf("<h1>Deleted Files To FTP</h1>");

      foreach($this->FilesToRemove as $file)
      {
        printf($file . '<br/>');
      }
    }

    /**
     * (void)
     * Make the FTP environment equal to the local one (main method)
     *
     * @access public
     * @return
     */
    public function equalEnvironments()
    {
      $json_path = $this->JSONStoredStatusPath;

      if($this->checkIfJSONFileExists($json_path))
      {
        if($getFirstStatus = $this->dumpDataFromJSON($json_path))
        {

          // Open Connection
          $this->openFTPConnection($this->FTPHost, $this->FTPUser, $this->FTPPassword);

          if(!$this->FTPConnection)
            die("FTP is not valid");

          if($this->findDifferences())
          {
            foreach($this->FilesToAdd as $file)
            {
              $remote_file_dir = $this->FTPDirectory . str_replace('../', '/', $file);
              $file = $file;

              $file_extension = pathinfo($remote_file_dir, PATHINFO_EXTENSION);

              // We check if this dir exists, otherwise it will be created
              $this->makeFTPPath($this->FTPConnection, $remote_file_dir);

              ftp_put($this->FTPConnection, $remote_file_dir, $file, FTP_ASCII);
            }

            // Output or do any action with the updated files
            foreach($this->FilesToUpdate as $file)
            {
              $remote_file_dir = $this->FTPDirectory . str_replace('../', '/', $file);
              $file = $file;

              $file_extension = pathinfo($remote_file_dir, PATHINFO_EXTENSION);

              ftp_put($this->FTPConnection, $remote_file_dir, $file, FTP_ASCII);
            }

            // Output or do any action with the removed files
            foreach($this->FilesToRemove as $file)
            {
              $remote_file_dir = $this->FTPDirectory . str_replace('../', '/', $file);

              @ftp_delete($this->FTPConnection, $remote_file_dir);
            }

            if($this->ShowDebug)
              $this->showDebugResults();

            // Finally we update the JSON
            $this->updateJSONPathContent($this->JSONStoredStatusPath, json_encode($this->FilesOutput));

          }
        }
      }
    }
}
