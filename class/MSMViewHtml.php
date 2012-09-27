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

// Helper classes
include(MSM_PATH.'class/UIForms.php');
include(MSM_PATH.'class/UITableForms.php');
include(MSM_PATH.'class/UIPages.php');

/**
* HTML view class
* @author Gusts 'gusC' Kaksis <gusts.kaksis@graftonit.lv>
*/
class MSMViewHtml extends MSMView {
	/**
	* Constraint type cast
	* MySQL values to readable values
	* @var array
	*/
	private $constraint_types = array(
		'p' => 'primary',
		'f' => 'foreign'
	);
	/**
	* Object type cast
	* MySQL values to readable values
	* @var array
	*/
	private $object_types = array(
		'BASE TABLE' => 'table',
		'VIEW' => 'view'
	);
	/**
	* Number of rows to display per-page
	* @var integer
	*/
	private $rows_per_page = 20;
	
	/**
	* Process user input
	* @return boolean
	*/
	public function input(){
		if (isset($_POST['do_login'])){
			if ($this->inputLogin()){
				URI::redirect();
				exit;
			}
			return false;
		} else if (isset($_GET['logout'])){
			$this->model->disconnect();
			URI::redirect(MSM_URL);
			exit;
		} else if (isset($_POST['do_export'])){
			$table = '';
			$options = array(
				'data' => isset($_POST['data']) ? $_POST['data'] : 'all',
				'drop' => isset($_POST['drop']) && intval($_POST['drop']) > 0
			);
			if (($filename = $this->model->export($table, $options)) !== false){
				$filepath = MSM_PATH.'tmp/'.$filename;
				header('Content-type: text/plain');
				header('Content-Length: '.filesize($filepath));
				header('Content-Disposition: attachment;filename='.$filename);
				header('Content-Transfer-Encoding: binary');
				
				$fh = fopen($filepath, 'rb');
				fpassthru($fh);
				fclose($fh);
				
				unlink($filepath);
				exit;
			}
		} else if (isset($_POST['do_import'])){
			$options = array(
				//'transaction' => isset($_POST['transaction']) && intval($_POST['transaction']) > 0
			);
			if (is_uploaded_file($_FILES['file']['tmp_name'])){
				$filename = 'import_'.date('YmdHi').rand(1000, 9999).'.sql';
				if (move_uploaded_file($_FILES['file']['tmp_name'], MSM_PATH.'tmp/'.$filename)){
					if ($this->model->import($filename, $options)){
						unlink(MSM_PATH.'tmp/'.$filename);
						URI::redirect('?ok=1');
						exit;
					}
					unlink(MSM_PATH.'tmp/'.$filename);
				} else {
					URI::redirect('?err=2');
					exit;
				}
			} else {
				URI::redirect('?err=1');
				exit;
			}
			return false;
		}
		return true;
	}
	/**
	* Process login information
	* @return boolean
	*/
	private function inputLogin(){
		$res = false;
		// Read input
		$host = '';
		if (isset($_POST['host'])){
			$host = trim($_POST['host']);
		}
		$port = '';
		if (isset($_POST['port'])){
			$port = trim($_POST['port']);
		}
		$dbname = '';
		if (isset($_POST['dbname'])){
			$dbname = trim($_POST['dbname']);
		}
		$user = '';
		if (isset($_POST['user'])){
			$user = trim($_POST['user']);
		}
		$pass = '';
		if (isset($_POST['pass'])){
			$pass = $_POST['pass'];
		}
		// Set credentials
		if (strlen($host) > 0){
			if (strlen($port) > 0){
				$this->model->setServer($host, $port);
			} else {
				$this->model->setServer($host);
			}
		}
		if (strlen($dbname) > 0){
			$this->model->setDatabase($dbname);
			$res = true;
		}
		if (strlen($user) > 0){
			if (strlen($pass) > 0){
				$this->model->setUser($user, $pass);
			} else {
				$this->model->setUser($user);
			}
			$res = true;
		}
		if ($res){
			// Try connection
			if ($this->model->connect()){
				return true;
			}
		}
		return false;
	}
	/**
	* Display a view
	*/
	public function view($full_page = true){
		if ($full_page){
			echo '<!DOCTYPE html> 
<html lang="lv"> 
<head> 
	<meta charset="utf-8">
	<title>MySQL Manager 1.0alpha</title>
	<meta http-equiv="Content-Language" content="en" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<meta http-equiv="Lang" content="en">
	<meta name="copyright" content="Gusts \'gusC\' Kaksis">
	<meta name="author" content="Gusts \'gusC\' Kaksis">  
	<meta name="description" content="Web based MySQL administration tool">
	<meta name="keywords" content="postgresql, psql">
	<link rel="stylesheet" type="text/css" href="/assets/css/main.css" media="screen, projection">
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6/jquery.min.js"></script>
	<script type="text/javascript" src="/assets/js/main.js"></script>
</head>
<body>
	<div id="holder">
		<div id="wrap">
			<!-- HEADER -->
			<header>';
		$this->viewMenu();
		echo '
			</header>
			<!-- // HEADER -->
			<!-- CONTENT -->
			<div id="content">';
		$this->viewContent();
		echo '
			</div>
			<!-- // CONTENT -->
		</div>
		<!-- FOOTER -->
		<footer>';
		$this->viewFooter();
		echo '
		</footer>
		<!-- // FOOTER -->
	</div>
</body>
</html>';
		} else {
			echo '
<div id="psm">';
			$this->viewMenu();
			$this->viewContent();
			echo '
</div>';
		}
		return true;
	}
	/**
	* Display menu bar
	*/
	private function viewMenu(){
		if ($this->model->isConnected()){
			echo '
				<ul id="psm-menu">
					<li class="structure"><a href="'.MSM_URL.'" title="Display database structure">Structure</a></li>
					<li class="functions"><a href="'.MSM_URL.'functions/" title="Display database functions">Functions</a></li>
					<li class="triggers"><a href="'.MSM_URL.'triggers/" title="Display database triggers">Triggers</a></li>
					<li class="info"><a href="'.MSM_URL.'info/" title="Display database information">Info</a></li>
					<li class="sql"><a href="'.MSM_URL.'sql/" title="Execute SQL query">SQL</a></li>
					<li class="io"><a href="'.MSM_URL.'io/" title="Import or export data">Import/Export</a></li>
					<li class="logout"><a href="'.MSM_URL.'?logout=1" title="Log-out">Log-out</a></li>
				</ul>';
		}
	}
	/**
	* Display login form
	*/
	private function loginForm(){
		echo '
					<form method="post" action="" id="psm-login">
						<h1>Authorization</h1>';
		echo UIForms::hidden('do_login', 1);
		if ($this->model->hasErrors()){
			echo UIForms::error($this->model->getErrors());
		}
		if (!defined('MSM_DB_HOST')){
			echo UIForms::input('host', 'Host:', isset($_POST['host']) ? $_POST['host'] : '');
		}
		if (!defined('MSM_DB_PORT')){
			echo UIForms::input('port', 'Port:', isset($_POST['port']) ? $_POST['port'] : '');
		}
		if (!defined('MSM_DB_NAME')){
			echo UIForms::input('dbname', 'Database:', isset($_POST['dbname']) ? $_POST['dbname'] : '');
		}
		if (!defined('MSM_DB_USER')){
			echo UIForms::input('user', 'User:', isset($_POST['user']) ? $_POST['user'] : '');
			echo UIForms::password('pass', 'Password:');
		}
		echo UIForms::submit('login', 'Log-in');
		echo '
					</form>';
	}
	/**
	* Display contents
	*/
	private function viewContent(){
		if ($this->model->isConnected()){
			$route = $this->route;
			$main = '';
			if (count($route) > 0){
				$main = array_shift($route);
			}
			$object = '';
			if (count($route) > 0){
				$object = array_shift($route);
			}
			$task = '';
			if (count($route) > 0){
				$task = array_shift($route);
			}
			switch ($main){
				case 'info':
					$this->serverInfo();
					break;
				case 'functions':
					$this->functionList();
					break;
				case 'triggers':
					$this->triggerList();
					break;
				case 'table':
					if ($task == 'data'){
						$this->tableData($object);
					} else {
						$this->tableInfo($object);
					}
					break;
				case 'view':
					if ($task == 'data'){
						$this->tableData($object);
					} else {
						$this->viewInfo($object);
					}
					break;
				case 'sql':
					$this->sqlForm();
					break;
				case 'io':
					$this->ioForm();
					break;
				default:
					$this->objectList((isset($_GET['type']) ? $_GET['type'] : null));
					break;
			}
		} else {
			$this->loginForm();
		}
	}

	private function ioForm(){
		$table = '';
		$data_type = isset($_POST['data']) ? $_POST['data'] : 'all';
		$drop = isset($_POST['drop']) && intval($_POST['drop']) > 0;
		echo '
					<form method="post" action="" id="psm-export">
						<h1>Export</h1>';
		echo UIForms::hidden('do_export', 1);
		if ($this->model->hasErrors()){
			if (isset($_POST['do_export'])){
				echo UIForms::error($this->model->getErrors());
			}
		}
		$data = array(
			'all' => 'Data & Structure',
			'structure' => 'Structure Only',
			'data' => 'Data Only'
		);
		echo UIForms::select('data', 'Export:', $data, $data_type);
		echo UIForms::checkbox('drop', 'Include drop commands', 1, $drop);
		echo UIForms::submit('submit', 'Export SQL file');
		echo '
					</form>';
		//$transaction = isset($_POST['transaction']) && intval($_POST['transaction']) > 0;
		echo '
					<form method="post" action="" id="psm-import" enctype="multipart/form-data">
						<h1>Import</h1>';
		echo UIForms::hidden('do_import', 1);
		if ($this->model->hasErrors()){
			if (isset($_POST['do_import'])){
				echo UIForms::error($this->model->getErrors());
			}
		} else if (isset($_GET['err'])){
			echo UIForms::error('Error uploading SQL file');
		} else if (isset($_GET['ok'])){
			echo UIForms::info('SQL file imported successfully');
		}
		echo UIForms::upload('file', 'File to import:');
		//echo UIForms::checkbox('transaction', 'Use transaction to prevent partial restoration', 1, $transaction);
		echo UIForms::submit('submit', 'Import SQL file');
		echo '
					</form>';
	}
	
	private function sqlForm(){
		$query = '';
		$test = false;
		if (isset($_POST['do_sql'])){
			$query = trim($_POST['query']);
			$test = (isset($_POST['test']) && intval($_POST['test']) > 0);
		}
		echo '
					<form method="post" action="" id="psm-query">
						<h1>Execute SQL query</h1>';
		echo UIForms::hidden('do_sql', 1);
		if ($this->model->hasErrors()){
			echo UIForms::error($this->model->getErrors());
		}
		echo UIForms::textarea('query', 'SQL query:', $query, array('class' => 'large'));
		echo UIFOrms::checkbox('test', 'Test query (transaction encapsulated)', 1, $test);
		echo UIForms::submit('execute', 'Execute');
		echo UIForms::submit('results', 'Execute With Results');
		if (isset($_POST['do_sql'])){
			if ($test){
				$this->model->exec('START TRANSACTION');
			}
			$start = microtime(true);
			$r = $this->model->exec($query);
			$end = microtime(true);
			$rows = 0;
			if ($r === false){
				echo UIForms::error($this->model->getErrors(), true);
				echo '<p>Status: Failed</p>';
			} else {
				$rows = intval(@mysql_affected_rows($r)); // Yes yes the bad @ sign,  I know
				if (isset($_POST['results'])){
					$this->displayResults($r);
					echo '<p>Rows in total: '.$this->model->count($r).'</p>';
				} else {
					echo '<p>Status: OK</p>';
					echo '<p>Affected rows: '.$rows.'</p>';
				}
				$this->model->free($r);
			}
			if ($test){
				$this->model->exec('ROLLBACK TRANSACTION');
			}
			echo '<p>Execution time: '.number_format($end - $start, 2, '.', '').'</p>';
		}
		echo '
					</form>';
	}
	private function displayResults($r){
		echo '
					<table>
						<thead>
							<tr>';
		for ($i = 0; $i < mysql_num_fields($r); $i ++){
			echo '
								<th>'.htmlspecialchars(mysql_field_name($r, $i)).'</th>';
		}
		echo '
							</tr>
						</thead>
						<tbody>';
		$c = $this->model->count($r);
		for ($i = 0; $i < $c; $i ++){
			$row = $this->model->fetchRow($r, $i);
			echo '
							<tr>';
			foreach ($row as $field){
				echo '
								<td><pre>'.htmlspecialchars($field).'</pre></td>';
			}
			echo '
							</tr>';
		}
		echo '
						</tbody>
					</table>';
	}
	private function serverInfo(){
		echo '
					<h1>Info</h1>
					<table>
						<thead>
							<tr>
								<th>Field</th>
								<th>Value</th>
							</tr>
						</thead>
						<tbody>';
		if (($info = $this->model->getInfo()) !== false){
			echo '
							<tr>
								<th>Client version</th>
								<td>'.$info['client_version'].'</td>
							</tr>
							<tr>
								<th>Client encoding</th>
								<td>'.$info['client_encoding'].'</td>
							</tr>
							<tr>
								<th>Server version</th>
								<td>'.$info['server_version'].'</td>
							</tr>
							<tr>
								<th>Server connection</th>
								<td>'.$info['server_connection'].'</td>
							</tr>';
		} else {
			echo '
							<tr>
								<td colspan="2">No connection available</td>
							</tr>';
		}
		echo '
						</tbody>
					</table>';
	}
	private function triggerList(){
		echo '
					<h1>Triggers</h1>
					<table>
						<thead>
							<tr>
								<th>Name</th>
								<th>Owner</th>
								<th>Language</th>
								<th>Prototype</th>
								<th>Returns</th>
								<th>Comments</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getFunctions()) !== false){
			$c = $this->model->count($r);
			for ($i = 0; $i < $c; $i ++){
				$function = $this->model->fetch($r, $i);
				echo '
							<tr>
								<td>'.htmlspecialchars($function['name']).'</td>
								<td>'.htmlspecialchars($function['owner']).'</td>
								<td>'.$function['language'].'</td>
								<td>'.htmlspecialchars($function['prototype']).'</td>
								<td>'.htmlspecialchars($function['returns']).'</td>
								<td>'.htmlspecialchars($function['comment']).'</td>
							</tr>';
			}
		}
		echo '
						</tbody>
					</table>';
	}
	private function functionList(){
		echo '
					<h1>Functions</h1>
					<table>
						<thead>
							<tr>
								<th>Name</th>
								<th>Owner</th>
								<th>Language</th>
								<th>Prototype</th>
								<th>Returns</th>
								<th>Comments</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getFunctions()) !== false){
			while (($function = $this->model->fetch($r)) !== false){
				echo '
							<tr>
								<td>'.htmlspecialchars($function['name']).'</td>
								<td>'.htmlspecialchars($function['owner']).'</td>
								<td>'.$function['language'].'</td>
								<td>'.htmlspecialchars($function['prototype']).'</td>
								<td>'.htmlspecialchars($function['returns']).'</td>
								<td>'.htmlspecialchars($function['comment']).'</td>
							</tr>';
			}
		}
		echo '
						</tbody>
					</table>';
	}
	private function viewInfo($view){
		echo '
					<h1><a href="../../">Structure</a> &gt; '.$view.'</h1>
					<table>
						<thead>
							<tr>
								<th>Name</th>
								<th>Type</th>
								<th>Not NULL</th>
								<th>Default</th>
								<th>Is Serial</th>
								<th>Comment</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getColumns($view)) !== false){
			$c = $this->model->count($r);
			for ($i = 0; $i < $c; $i ++){
				$column = $this->model->fetch($r, $i);
				echo '
							<tr>
								<td>'.htmlspecialchars($column['name']).'</td>
								<td>'.$column['type'].'</td>
								<td>'.($column['is_not_null'] == 't' ? '<span class="yes">YES</span>' : '<span class="no">NO</span>').'</td>
								<td>'.($column['has_default'] == 't' ? htmlspecialchars($column['default_value']) : '').'</td>
								<td>'.($column['is_serial'] == 't' ? '<span class="yes">YES</span>' : '<span class="no">NO</span>').'</td>
								<td>'.htmlspecialchars($column['comment']).'</td>
							</tr>';
			}
			$this->model->free($r);
		}
		echo '
						</tbody>
					</table>
					<h2>Definition</h2>
					<pre>'.$this->model->getViewDefinition($view).'</pre>';
	}
	private function tableInfo($table){
		echo '
					<h1><a href="../../">Structure</a> &gt; '.$table.'</h1>
					<table>
						<thead>
							<tr>
								<th>Name</th>
								<th>Type</th>
								<th>NULL</th>
								<th>Key</th>
								<th>Default</th>
								<th>Extra</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getColumns($table)) !== false){
			while (($column = $this->model->fetch($r)) !== false){
				echo '
							<tr>
								<td>'.htmlspecialchars($column['Field']).'</td>
								<td>'.$column['Type'].'</td>
								<td>'.($column['Null'] == 'YES' ? '<span class="yes">YES</span>' : '<span class="no">NO</span>').'</td>
								<td>'.htmlspecialchars($column['Key']).'</td>
								<td>'.htmlspecialchars($column['Default']).'</td>
								<td>'.htmlspecialchars($column['Extra']).'</td>
							</tr>';
			}
			$this->model->free($r);
		}
		echo '
						</tbody>
						<tfoot>
							<tr>
								<th colspan="6"><p><a href="data/">View data</a></p></th>
							</tr>
						</tfoot>
					</table>
					<h2>Indexes</h2>
					<table>
						<thead>
							<tr>
								<th>Column</th>
								<th>Key</th>
								<th>Sequence</th>
								<th>Is Unique</th>
								<th>Collation</th>
								<th>Cardinality</th>
								<th>Packed</th>
								<th>Null</th>
								<th>Type</th>
								<th>Comment</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getIndexes($table)) !== false){
			while (($index = $this->model->fetch($r)) !== false){
				//Table	Non_unique	Key_name	Seq_in_index	Column_name	Collation	Cardinality	Sub_part	Packed	Null	Index_type	Comment
				echo '
							<tr>
								<td>'.htmlspecialchars($index['Column_name']).'</td>
								<td>'.htmlspecialchars($index['Key_name']).'</td>
								<td>'.htmlspecialchars($index['Seq_in_index']).'</td>
								<td>'.($index['Non_unique'] == '0' ? '<span class="yes">YES</span>' : '<span class="no">NO</span>').'</td>
								<td>'.htmlspecialchars($index['Collation']).'</td>
								<td>'.htmlspecialchars($index['Cardinality']).'</td>
								<td>'.htmlspecialchars($index['Packed']).'</td>
								<td>'.($index['Null'] == 'YES' ? '<span class="yes">YES</span>' : '<span class="no">NO</span>').'</td>
								<td>'.htmlspecialchars($index['Index_type']).'</td>
								<td>'.htmlspecialchars($index['Comment']).'</td>
							</tr>';
			}
			$this->model->free($r);
		}
		echo '
						</tbody>
					</table>';
	}
	private function tableData($table){
		echo '
					<h1><a href="../../../">Structure</a> &gt; <a href="../">'.$table.'</a> &gt; Data</h1>';
		$data_count = $this->model->getDataCount($table);
		if (($r = $this->model->getData($table, (isset($_GET['page']) ? $_GET['page'] * $this->rows_per_page : 0), $this->rows_per_page)) !== false){
			echo '
					<table>
						<thead>
							<tr>';
			$columns = $this->model->columnNames($r);
			foreach ($columns as $field){
				echo '
								<th>'.htmlspecialchars($field).'</th>';
			}
			echo '
							</tr>
						</thead>
						<tbody>';
			while (($data = $this->model->fetchRow($r)) !== false){
				echo '
							<tr>';
				foreach ($data as $field){
					echo '
								<td><pre>'.htmlspecialchars($field).'</pre></td>';
				}
				echo '
							</tr>';
			}
			$this->model->free($r);
			echo '
						</tbody>';
			if ($data_count > $this->rows_per_page){
				echo '
						<tfoot>
							<tr>
								<th colspan="'.count($columns).'">';
				echo UIPages::inTable(ceil($data_count / $this->rows_per_page), (isset($_GET['page']) ? $_GET['page'] : 0), $_GET);
				echo '
								</th>
							</tr>
						</tfoot>';
			}
			echo '
					</table>';
		}
	}
	private function objectList($type = null){
		echo '
					<h1>Structure</h1>
					<table>
						<thead>
							<tr>
								<th>Type</th>
								<th>Name</th>
								<th></th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getObjects()) !== false){
			while (($table = $this->model->fetchRow($r)) !== false){
				$show = true;
				if (!is_null($type) && $this->object_types[$table[1]] != $type){
					$show = false;
				}
				if ($show){
					echo '
							<tr>
								<td class="type '.$this->object_types[$table[1]].'">'.$this->object_types[$table[1]].'</td>
								<td><a href="'.$this->object_types[$table[1]].'/'.$table[0].'/">'.htmlspecialchars($table[0]).'</a></td>
								<td><a href="'.$this->object_types[$table[1]].'/'.$table[0].'/">Info</a> | <a href="'.$this->object_types[$table[1]].'/'.$table[0].'/data/">Data</a></td>
							</tr>';
				}
			}
		}
		echo '
						</tbody>
					</table>';
	}
	private function schemaList($title){
		echo '
					<h1>'.$title.'</h1>
					<table>
						<thead>
							<tr>
								<th>Type</th>
								<th>Name</th>
								<th>Owner</th>
								<th>Comments</th>
							</tr>
						</thead>
						<tbody>';
		if (($r = $this->model->getSchemas()) !== false){
			$c = $this->model->count($r);
			for ($i = 0; $i < $c; $i ++){
				$schema = $this->model->fetch($r, $i);
				echo '
							<tr>
								<td class="type schema">schema</td>
								<td><a href="schema/'.$schema['name'].'/">'.htmlspecialchars($schema['name']).'</a></td>
								<td>'.htmlspecialchars($schema['owner']).'</td>
								<td>'.htmlspecialchars($schema['comment']).'</td>
							</tr>';
			}
			$this->model->free($r);
		}
		echo '
						</tbody>
					</table>';
	}
	/**
	* Display footer
	*/
	private function viewFooter(){
		echo '<p>&copy; Gusts \'gusC\' Kaksis, 2012</p>';
	}
}
?>