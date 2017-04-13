<?php
/**
TODO API 
@author merlinbecker
@version 1.0
@date 31.03.2017

31.03.2017:
This is the second approach of a RESTful todo list.
The github repository can be found here
https://github.com/merlinbecker/Todo_API

a first (more UI based) version of this api can be found here:
https://github.com/beophyr/TaskList

let's see how far we can get this time!
---------------------------------------
***/

/**
 * headers: allow cross origin access
 **/
header("Access-Control-Allow-Origin: *");

/*
step zero:
include classes and set headers
*/
require_once 'classes/installer.class.php';
require_once 'classes/database.class.php';
$installer=Installer::sharedInstaller();

ob_start();

/**
todo: htaccess abfangen wenn eingestellt
**/

/**
first step:
get the path from the mod reqrite htaccess right and extract all variables needed.
**/

$accepts=explode(", ",$_SERVER['HTTP_ACCEPT']);
$method=$_SERVER['REQUEST_METHOD'];
$tempparams=explode("/",$_SERVER['REQUEST_URI']);
$params=array();
$params[]=$tempparams[2];
$params[]=$tempparams[3];
if($_SERVER['argc']>0){	
	$query=array();
	parse_str($_SERVER['argv'][0],$query);
}


/**
second step: check if server is set up right!
**/
$output=do_pre_checks($installer);
if($output!=""){
	//echo what is missing for installing if no one is trying to put new settings
  if(!(($method=="POST"||$method=="PUT")&&$params[0]=="settings")){
 	  http_response_code(501);
	  header('Content-Type: application/json');
	  echo json_encode($output);
    die();
  }
}


switch($params[0]){
  case "settings":
    //check,if admin permission
    if(is_admin()){
    if($method=="POST"||$method=="PUT"){
		http_response_code(201);
		$payload =(array)json_decode(file_get_contents('php://input'));
		foreach ($payload as $key=>$val){
			$installer->conf[$key]=trim($val);
		}
		$installer->saveConfig();
    }
	else{
		$output=$installer->getConfig();
	}
   }
   else{
     http_response_code(401);
     die();
   }
  break;
}

$possible_errors=ob_get_contents();
ob_end_clean();
if(strlen($possible_errors)>0){
	http_response_code(500);
	$output=$possible_errors;
}

header('Content-Type: application/json');
echo json_encode($output);


/** Functions **/

function do_pre_checks($installer){
	//check if server is installed
	$missing=$installer->checkInstallNeeds();
	if(count($missing)>0){
		$output=$missing;
		return $output;
	}
}

function is_admin(){
  if(isset(Installer::sharedInstaller()->conf['uses_basic_auth'],Installer::sharedInstaller()->conf['basic_auth_admin'],Installer::sharedInstaller()->conf['basic_auth_pw'])){
        if(Installer::sharedInstaller()->conf['uses_basic_auth']==1){
          return (Installer::sharedInstaller()->conf['basic_auth_admin']==$_SERVER['PHP_AUTH_USER']&&Installer::sharedInstaller()->conf['basic_auth_pw']==$_SERVER['PHP_AUTH_PW']);
        }
  }
  //return true if no user auth is given
  return true;
}
?>