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
include(MSM_PATH.'class/URI.php');
// Core classes
include(MSM_PATH.'class/MSMView.php');
include(MSM_PATH.'class/MSMModel.php');
include(MSM_PATH.'class/MSMViewHtml.php');
include(MSM_PATH.'class/MSMViewJson.php');

/**
* MySQL Manager controller class
* @author Gusts 'gusC' Kaksis <gusts.kaksis@graftonit.lv>
*/
class MSM {
	/**
	* Request URI object
	* @var URI
	*/
	private $uri = null;
	/**
	* MySQL Manager model object
	* @var MSMModel
	*/
	private $model = null;
	/**
	* MySQL Manager view object
	* @var MSMView
	*/
	private $view = null;
	
	/**
	* Initialize PgManager
	*/
	public function __construct(){
		$this->uri = new URI();
		$this->createModel();
		$this->createView();
	}
	
	/**
	* Create model object
	* @return void
	*/
	private function createModel(){
		$this->model = new MSMModel();
	}
	/**
	* Create a view object from route information
	* @return void
	*/
	private function createView(){
		$route = $this->uri->getPathStr();
		$route = substr($route, strlen(MSM_URL));
		if (substr($route, 0, 1) == '/'){
			$route = substr($route, 1);
		}
		if (substr($route, -1) == '/'){
			$route = substr($route, 0, -1);
		}
		$route = explode('/', $route);
		$output = 'html';
		if (count($route) > 0 && in_array($route[0], array('html', 'json'))){
			$output = array_shift($route);
		}
		switch ($output){
			case 'json':
				$this->view = new MSMViewJson($this->model, $route);
				break;
			case 'html':
			default:
				$this->view = new MSMViewHtml($this->model, $route);
				break;
		}
	}
	/**
	* Process user input
	* @return boolean
	*/
	public function input(){
		return $this->view->input();
	}
	/**
	* Process view
	* @return boolean 
	*/
	public function view(){
		return $this->view->view();
	}
}
?>