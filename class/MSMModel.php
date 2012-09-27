<?php
/*

Copyright (C) 2012 Gusts Kaksis <gusts.kaksis@gmail.com>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), 
to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, 
and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO 
THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR 
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, 
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

/**
* MySQL Manager model class
* @author Gusts 'gusC' Kaksis <gusts.kaksis@graftonit.lv>
*/
class MSMModel {
	private $connection = null;
	
	private $host = '';
	private $port = '';
	private $dbname = '';
	private $user = '';
	private $pass = '';
	
	private $hidden_schemas = array('pg_catalog', 'information_schema');
	
	private $errors = array();
	
	/**
	* Construct a MySQL Manager model
	*/
	public function __construct(){
		if (!$this->readSession()){
			if (defined('MSM_DB_HOST')){
				$this->host = MSM_DB_HOST;
			}
			if (defined('MSM_DB_PORT')){
				$this->port = MSM_DB_PORT;
			}
			if (defined('MSM_DB_NAME')){
				$this->dbname = MSM_DB_NAME;
			}
			if (defined('MSM_DB_USER')){
				$this->user = MSM_DB_USER;
			}
			if (defined('MSM_DB_PASS')){
				$this->pass = MSM_DB_PASS;
			}
		}
		if (strlen($this->user) > 0 && strlen($this->dbname) > 0){
			$this->connect();
		}
	}
	
	/**
	* Get all available schemas
	* @return array
	*/
	public function getSchemas(){
		return false;
	}
	/**
	* Get all available table like objects
	* @return resource
	*/
	public function getObjects(){
		$q = "SHOW FULL TABLES";
		return $this->exec($q);
	}
	/**
	* Get columns
	* @param string $tablename
	* @return resource
	*/
	public function getColumns($tablename){
		$q = "SHOW COLUMNS FROM ".$this->escape($tablename);
		return $this->exec($q);
	}
	/**
	* Get table indexes
	* @param string $tablename
	* @return resource
	*/
	public function getIndexes($tablename){
		$q = "SHOW INDEXES FROM ".$this->escape($tablename);
		return $this->exec($q);
	}
	/**
	* Get list of triggers
	* @return resource
	*/
	public function getTriggers(){
		$q = "SHOW TRIGGERS";
		return $this->exec($q);
	}
	/**
	* Get view definition
	* @param string $viewname
	* @return string
	*/
	public function getViewDefinition($viewname){
		$definition = '';
		$q = "SHOW CREATE VIEW ".$this->escape($viewname);
		$r = $this->exec($q);
		if ($this->count($r) > 0){
			list ($view, $definition) = $this->fetchRow($r);
		}
		return $definition;
	}
	/**
	* Get a list of functions
	* @return resource
	*/
	public function getFunctions(){
		$q = "SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Type = 'PROCEDURE'";
		return $this->exec($q);
	}
	/**
	* Total ammount of rows in table
	* @param string $tablename
	* @return integer
	*/
	public function getDataCount($tablename){
		$q = "SELECT count(*) FROM ".$this->escape($tablename);
		$r = $this->exec($q);
		if ($this->count($r) > 0){
			list ($count) = $this->fetchRow($r);
			return intval($count);
		}
		return 0;
	}
	/**
	* Total ammount of rows in table
	* @param string $tablename
	* @return integer
	*/
	public function getData($tablename, $offset, $limit){
		$q = "SELECT * FROM ".$this->escape($tablename)." LIMIT ".$limit." OFFSET ".$offset;
		return $this->exec($q);
	}
	/**
	* Get server info
	* @return array
	*/
	public function getInfo(){
		if ($this->isConnected()){
			return array(
				'client_version' => mysql_get_client_info(),
				'client_encoding' => mysql_client_encoding($this->connection),
				'server_connection' => mysql_get_host_info($this->connection),
				'server_version' => mysql_get_server_info($this->connection)
			);
		}
		return false;
	}
	/**
	* Import sql file
	* @param string $filename - filename in tmp directory
	* @param array $options - transaction - use transaction block to prevent partial restoration
	* @return boolean
	*/
	public function import($filename, $options){
		$cmd = MSM_DB_RESTORE_CMD;
		
		if (strlen($this->host) > 0) {
			$cmd .= ' -h '.escapeshellarg($this->host);
		}
		if (strlen($this->port) > 0) {
			$cmd .= ' -P '.escapeshellarg($this->port);
		}
		$cmd .= ' -u '.escapeshellarg($this->user);
		if (strlen($this->pass) > 0){
			$cmd .= ' -p'.escapeshellarg($this->pass);
		}
		
		$cmd .= ' '.escapeshellarg($this->dbname);
		$cmd .= ' < '.escapeshellarg(MSM_PATH.'tmp/'.$filename);
		
		$output = array();
		$return = 0;
		exec($cmd, $output, $return);
		if ($return == 0){
			return true;
		}
		$this->setError('Restore command exited with: '.$return);
		return false;
	}
	/**
	* Export sql file
	* @param string $tablename
	* @param array $options - data - for data to export, drop - to add drop commands
	* @return string - filename in tmp directory
	*/
	public function export($tablename, $options){
		$cmd = MSM_DB_DUMP_CMD;
		
		if (strlen($this->host) > 0) {
			$cmd .= ' -h '.escapeshellarg($this->host);
		}
		if (strlen($this->port) > 0) {
			$cmd .= ' -P '.escapeshellarg($this->port);
		}
		$cmd .= ' -u '.escapeshellarg($this->user);
		if (strlen($this->pass) > 0){
			$cmd .= ' -p'.escapeshellarg($this->pass);
		}
		
		//$cmd .= '  --compatible=ansi';
		
		switch ($options['data']){
			case 'structure':
				$cmd .= ' --no-data';
				if (!$options['drop']){
					$cmd .= ' --skip-add-drop-table';
				}
				break;
			case 'data':
				$cmd .= ' --no-create-info --skip-add-locks --complete-insert --skip-extended-insert';
				break;
			case 'all':
				$cmd .= ' --skip-add-locks --complete-insert --skip-extended-insert';
				if (!$options['drop']){
					$cmd .= ' --skip-add-drop-table';
				}
				break;
		}
		
		$filename = $this->dbname.'_';
		if (strlen($tablename) > 0){
				$filename .= $tablename.'_';
		}
		$filename .= date('YmdHi').'_'.$options['data'].'.sql';
		
		$cmd .= ' --result-file '.escapeshellarg(MSM_PATH.'tmp/'.$filename);
		$cmd .= ' '.escapeshellarg($this->dbname);
		if (strlen($tablename) > 0){
			$cmd .= ' '.escapeshellarg($tablename);
		}
		
		$output = array();
		$return = 0;
		exec($cmd, $output, $return);
		if ($return == 0){
			return $filename;
		}
		$this->setError('Dump command exited with: '.$return);
		if (is_file(MSM_PATH.'tmp/'.$filename)){
			unlink(MSM_PATH.'tmp/'.$filename);
		}
		return false;
	}
	
	
	/**
	* Escape string
	* @param string $string
	* @return string
	*/
	public function escape($string){
		if ($this->isConnected()){
			return mysql_real_escape_string($string, $this->connection);
		}
		return mysql_real_escape_string($string);
	}
	/**
	* Execute query
	* @param string $query
	* @return resource - or false on error
	*/
	public function exec($query){
		if ($this->isConnected()){
			$r = @mysql_query($query, $this->connection); // argh, there is no possibility to do it without @ :(
			if ($r === false){
				$this->setError(mysql_error($this->connection));
			}
			return $r;
		} else {
			$this->setError('Not connected to any database');
		}
		return false;
	}
	/**
	* Get number of rows returned by query
	* @param resource $resource
	* @return integer
	*/
	public function count($resource){
		if ($this->isConnected()){
			if (is_resource($resource)){
				return mysql_num_rows($resource);
			} else {
				$this->setError('Parameter is not a resource');
			}
		} else {
			$this->setError('Not connected to any database');
		}
		return false;
	}
	/**
	* Get column names from query resource
	* @param resource $resource
	* @param array
	*/
	public function columnNames($resource){
		$list = array();
		if (is_resource($resource)){
			for ($i = 0; $i < mysql_num_fields($resource); $i ++){
				array_push($list, mysql_field_name($resource, $i));
			}
		} else {
			$this->setError('Parameter is not a resource');
		}
		return $list;
	}
	/**
	* Fetch a row
	* @param resource $resource
	* @return array
	*/
	public function fetchRow($resource){
		if ($this->isConnected()){
			if (is_resource($resource)){
				return mysql_fetch_row($resource);
			} else {
				$this->setError('Parameter is not a resource');
			}
		} else {
			$this->setError('Not connected to any database');
		}
		return false;
	}
	/**
	* Fetch a row as an array
	* @param resource $resource
	* @return array
	*/
	public function fetch($resource){
		if ($this->isConnected()){
			if (is_resource($resource)){
				return mysql_fetch_assoc($resource);
			} else {
				$this->setError('Parameter is not a resource');
			}
		} else {
			$this->setError('Not connected to any database');
		}
		return false;
	}
	/**
	* Free up some ram
	* @param resource $resource
	* @return boolean
	*/
	public function free($resource){
		if ($this->isConnected()){
			if (is_resource($resource)){
				return mysql_free_result($resource);
			} else {
				$this->setError('Parameter is not a resource');
			}
		} else {
			$this->setError('Not connected to any database');
		}
		return false;
	}
	
	/**
	* Set server connection parameters
	* @param string $host
	* @param string $port
	*/
	public function setServer($host, $port='5432'){
		$this->host = $host;
		$this->port = $port;
	}
	/**
	* Set database name
	* @param string $dbname
	*/
	public function setDatabase($dbname){
		$this->dbname = $dbname;
	}
	/**
	* Get database name
	* @return string
	*/
	public function getDatabase(){
		return $this->dbname;
	}
	/**
	* Set user credentials
	* @param string $user
	* @param string $pass
	*/
	public function setUser($user, $pass=''){
		$this->user = $user;
		$this->pass = $pass;
	}
	/**
	* Get username
	* @return string
	*/
	public function getUsername(){
		return $this->user;
	}
	
	/**
	* Get connection state
	*/
	public function isConnected(){
		return is_resource($this->connection);
	}
	/**
	* Open database connection
	* @return boolean - true on success
	*/
	public function connect(){
		if ($this->isConnected()){
			$this->disconnect();
		}
		$server = $this->host;
		if (strlen($this->port) > 0){
			$server .= ':'.$this->port;
		}		
		$this->connection = @mysql_connect($server, $this->user, $this->pass); // argh, there is no possibility to do it without @ :(
		if ($this->connection !== false){
			if (!mysql_select_db($this->dbname, $this->connection)){
				$this->setError('Could not find a database');
				$this->deleteSession();
				return false;
			}
		} else {
			$this->setError('Could not connect, try different credentials');
			$this->deleteSession();
			return false;
		}
		$this->writeSession();
		return true;
	}
	/**
	* Close database connection
	* @return void
	*/
	public function disconnect(){
		if ($this->isConnected()){
			mysql_close($this->connection);
		}
		$this->deleteSession();
	}
	
	/**
	* Check weather session has been started
	* @return boolean
	*/
	public function sessionStarted(){
		if (session_id() != '') {
			return true;
		}
		return false;
	}
	/**
	* Read connection credentials from session
	* @return boolean - true if session has been started and some data could be found
	*/
	private function readSession(){
		$res = false;
		if ($this->sessionStarted()) {
			if (isset($_SESSION['msm_db_host'])){
				$this->host = $_SESSION['msm_db_host'];
				$res = true;
			}
			if (isset($_SESSION['msm_db_port'])){
				$this->port = $_SESSION['msm_db_port'];
				$res = true;
			}
			if (isset($_SESSION['msm_db_dbname'])){
				$this->dbname = $_SESSION['msm_db_dbname'];
				$res = true;
			}
			if (isset($_SESSION['msm_db_user'])){
				$this->user = $_SESSION['msm_db_user'];
				$res = true;
			}
			if (isset($_SESSION['msm_db_pass'])){
				$this->pass = $_SESSION['msm_db_pass'];
				$res = true;
			}
		}
		return $res;
	}
	/**
	* Write connection credentials to session
	* @return boolean - true if session has been started
	*/
	private function writeSession(){
		if ($this->sessionStarted()) {
			$_SESSION['msm_db_host'] = $this->host;
			$_SESSION['msm_db_port'] = $this->port;
			$_SESSION['msm_db_dbname'] = $this->dbname;
			$_SESSION['msm_db_user'] = $this->user;
			$_SESSION['msm_db_pass'] = $this->pass;
			return true;
		}
		return false;
	}
	/**
	* Delete connection credentials from session
	* @return void
	*/
	private function deleteSession(){
		if ($this->sessionStarted()) {
			if (isset($_SESSION['msm_db_host'])){
				unset($_SESSION['msm_db_host']);
			}
			if (isset($_SESSION['msm_db_port'])){
				unset($_SESSION['msm_db_port']);
			}
			if (isset($_SESSION['msm_db_dbname'])){
				unset($_SESSION['msm_db_dbname']);
			}
			if (isset($_SESSION['msm_db_user'])){
				unset($_SESSION['msm_db_user']);
			}
			if (isset($_SESSION['msm_db_pass'])){
				unset($_SESSION['msm_db_pass']);
			}
		}
	}
	
	/**
	* Store errors
	* @param string $message
	*/
	private function setError($message){
		array_push($this->errors, $message);
	}
	/**
	* Check weather there are any errors
	* @return boolean
	*/
	public function hasErrors(){
		return (count($this->errors) > 0);
	}
	/**
	* Get the list of errors
	* @return array
	*/
	public function getErrors(){
		return $this->errors;
	}
}
?>