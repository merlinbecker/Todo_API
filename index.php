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
  case "users":
	if(is_admin()){
		$database=new Database();
		if($method=="PUT") {
			$payload =json_decode(file_get_contents('php://input'));
			if($payload->username==""||$payload->user_email==""||$payload->user_password==""||($payload->user_password!=$payload->user_pw_repeat)){
				http_response_code(400);
				die();
			}
			else{
			$sql="INSERT INTO `".Installer::sharedInstaller()->conf['db_database']."`.`tl_users` (u_name,u_email,u_password) VALUES(:u_name, :u_email, :u_password) ON DUPLICATE KEY UPDATE   u_name=:u_name, u_email=:u_email, u_password=:u_password";
			$database->query($sql);
			$database->bind(':u_name',$payload->username);
			$database->bind(':u_email',$payload->user_email);
			$database->bind(':u_password',password_hash($payload->user_password, PASSWORD_DEFAULT));
			$database->execute();
			$p_error=$database->error;
			if($p_error==""){
				http_response_code(201);
				die();
			}
			echo $p_error;
			}
		}
		else if($method=="DELETE"){
			$payload =json_decode(file_get_contents('php://input'));
			if(!is_array($payload)){
				http_response_code(400);
				die();
			}
			
			$database->beginTransaction();
			foreach ($payload as $p){
			if($p->u_id==""||$p->u_name==""||$p->u_email==""){
				$database->cancelTransaction();
				http_response_code(400);
				die();
			}
			else{
				$sql="DELETE FROM `".Installer::sharedInstaller()->conf['db_database']."`.`tl_users` WHERE u_id=:u_id AND u_name=:u_name AND u_email=:u_email";
				$database->query($sql);
				$database->bind(':u_id',$p->u_id);
				$database->bind(':u_name',$p->u_name);
				$database->bind(':u_email',$p->u_email);
				$database->execute();
				if($database->error!=""){
					$database->cancelTransaction();
					http_response_code(400);
					die();
				}
			}
			}
			$database->endTransaction();
		}
		else{
			//TODO: Für später aufheben!
			//, FROM_UNIXTIME(last_login, '%Y-%m-%d %H:%i:%s') 
			$sql="SELECT u_id,u_name,u_email FROM `".Installer::sharedInstaller()->conf['db_database']."`.`tl_users`";
			$database->query($sql);
			$database->execute();
			echo $database->error;
			$output=$database->resultset();
		}
	  }
	else{
		http_response_code(401);
		die();
	}
  break;
	case "tasks":
		echo "ITS A SPECIFIC TASK, BUT IT IS NOT IMPLEMENTED YET!";
	break;
	default:
	//bail out if username is empty
	if($params[0]==""){
		http_response_code(404);
		die();
	}	
	//check if user is logged in
	$database=new Database();
	$sql="SELECT * FROM tl_users WHERE u_name=:u_name";
	$database->query($sql);
	$database->bind(':u_name',$params[0]);
	$user=$database->single();
	if($user['u_name']==$_SERVER['PHP_AUTH_USER']&&password_verify($_SERVER['PHP_AUTH_PW'],$user['u_password']))
	{		
		//if second param ist a number, than it's a task, else it's a project
		//if the project is 'all' or empty, then it's all tasks for that user
		//if the method is PUT, then a todo is put here
		if($params[1]==""){
			if($method=="PUT"){
				
			}
		}
		
		//if()
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