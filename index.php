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

$output=do_pre_checks($installer);

switch($params[0]){
  case "settings":
    if($method=="POST"||$method=="PUT"){
		http_response_code(201);
		header("Location: ".$_SERVER['REQUEST_URI']);
		echo "HIER DIE METHODE!! ".$method;
		
    }
    echo $installer->getConfig();
  break;
}


print_r($accepts);
echo "Method: ".$method."\n";

print_r($tempparams);

if($_SERVER['argc']>0){	
	$query=array();
	parse_str($_SERVER['argv'][0],$query);
}

echo "Query:".print_r($query);
print_r($_SERVER);


$possible_errors=ob_get_contents();
ob_end_clean();
if(strlen($possible_errors)>0){
	$output['status']='error';
	if(!isset($output['errordescription']))$output['errordescription']="";
	if(is_array($output['errordescription']))
		$output['errordescription'][]=$possible_errors;
	else	
		$output['errordescription'].=$possible_errors;
	
}
echo json_encode($output);


/** Functions **/

function do_pre_checks($installer){
	//check if server is installed
	$missing=$installer->checkInstallNeeds();
	if(count($missing)>0){
		$output['status']='error';
		$output['errordescription']='incomplete_installation';
		$output['payload']=$missing;
		return $output;
	}
}
?>