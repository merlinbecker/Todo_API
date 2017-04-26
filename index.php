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

20.04.2017:
TODO: Write test cases and then: 
TODO: Refactor 

a first (more UI based) version of this api can be found here:
https://github.com/beophyr/TaskList

let's see how far we can get this time!
---------------------------------------
***/

/**
 * headers: allow cross origin access
 **/
error_reporting(E_ERROR | E_WARNING | E_PARSE);
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

/**
 * @TODO make a helper class out of this (and the absolute url)
 * **/
$accepts=explode(", ",$_SERVER['HTTP_ACCEPT']);
$method=$_SERVER['REQUEST_METHOD'];


$url_prefix=str_replace("index.php","",$_SERVER['SCRIPT_NAME']);

$temp_url="/".str_replace($url_prefix, "", $_SERVER['REQUEST_URI']);

//remove query from request uri
$query_params=substr($temp_url,strpos($temp_url,"?")+1);



if(!strpos($temp_url,"?")===false)
	$req_uri=substr($temp_url,0,strpos($temp_url,"?"));
else 
	$req_uri=$temp_url;
$tempparams=explode("/",$req_uri);


$params=array();
$params[]=$tempparams[1];
$params[]=$tempparams[2];
$params[]=$tempparams[3];

$url_query=array();
parse_str($query_params,$url_query);

if($_SERVER['argc']>0){	
	echo "hier her!!";
	parse_str($_SERVER['argv'][0],$url_query);
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
		if($method=="POST") {
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
	if(is_valid_user($user))
	{		
		//if second param ist a number, than it's a task, else it's a project
		//if the project is 'all' or empty, then it's all tasks for that user
		//if the method is PUT, then a todo is put here
		/**
		 * TODO: even allow if posted under a project and keep this project as default.
		 * this is a default task creation routine 
		 * 
		 * @TODO auslagern
		 * @TODO allow CSV values like 1,2,3
		 * **/
		if($params[1]==""){
			if ($method=="GET"){
				/*
					usersummaryObj {
					projects_count:
					integer
					projects_rel:
					string
					todos_count:
					integer
					todos_rel:
					string
				*/
				//first, get number of projects for that user
				$sql="SELECT COUNT(project_id) as projects_count FROM tl_projects WHERE user_id=:user_id";
				$database->query($sql);
				$database->bind(":user_id",$user['u_id']);
				$res=$database->single();
				
				echo $database->error;
				
				$output['projects_count']=$res['projects_count'];
				$absolute_url = full_url( $_SERVER );
				$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."projects/";
				$output['projects_rel']=$absolute_url;
				
				//get count of all todos
				$sql="SELECT COUNT(task_id) as todos_count FROM tl_tasks,tl_users_tasks WHERE tl_tasks.status=:status AND tl_users_tasks.t_id=tl_users_tasks.user_id AND tl_users_tasks.user_id=:user_id";
				$database->query($sql);
				$database->bind(":status","todo");
				$database->bind(":user_id",$user['u_id']);
				$res=$database->single();
				
				echo $database->error;
				
				$output['todos_count']=$res['todos_count'];
				$absolute_url = full_url( $_SERVER );
				$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."tasks?show=todo";
				$output['todos_rel']=$absolute_url;
				
			}}
			
			if($params[1]=="tasks"||$params[1]=="projects"){
				if($method=="GET"){
					if($params[1]=="tasks"){
						if(is_numeric($params[2])){
							$task_id=$params[2];
							$sql="SELECT task_id,description as task_description,urgent,important,status,";
							$sql.="IF(deadline=0,'-',FROM_UNIXTIME(deadline,'%Y-%m-%d %H:%i:%s')) as deadline,";
							$sql.="IF(repeat_interval=0,'-',FROM_UNIXTIME(repeat_interval,'%Y-%m-%d %H:%i:%s')) as repeat_interval,";
							$sql.="IF(repeat_since=0,'-',FROM_UNIXTIME(repeat_since,'%Y-%m-%d %H:%i:%s')) as repeat_since,";
							$sql.="IF(repeat_until=0,'-',date_format(repeat_until,'%Y-%m-%d %H:%i:%s')) as repeat_until";
							$sql.=" FROM tl_tasks WHERE task_id=:task_id";
							
							$database->query($sql);
							$database->bind(":task_id",$task_id);
							$output=$database->single();
							echo $database->error;
							if($database->rowCount()==0){
								//no task found under given id
								http_response_code(404);
								die();
							}
							else{
								//get projects for task
								$database->query("SELECT project_name FROM tl_projects,tl_projects_tasks WHERE tl_projects_tasks.project_id=tl_projects.project_id AND tl_projects_tasks.task_id=:task_id");
								$database->bind(":task_id",$task_id);
								$projs=$database->resultset();
								echo $database->error;
								if(!is_array($output['projects']))$output['projects']=array();
								foreach($projs as $proj){
									$output['projects'][]=$proj['project_name'];
								}
							}
						}
						else{
								/**
								 *  description:
									status:
									rel: 
								 **/
								$sql="SELECT task_id, description, status FROM tl_tasks, tl_users_tasks WHERE tl_users_tasks.t_id=task_id AND tl_users_tasks.user_id=:user_id";
								
								if(isset($url_query['show'])){
									$sql.=" AND status=:status";
								}
								
								$database->query($sql);
								$database->bind(':user_id',$user['u_id']);
								
								if(isset($url_query['show'])){
									$database->bind(":status",$url_query['show']);
								}
								
								
								$result=$database->resultset();
								echo $database->error;
								
								foreach($result as &$item){
									//generate url
									$absolute_url = full_url( $_SERVER );
									$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."tasks/".$item['task_id'];
									$item['rel']=$absolute_url;
								}
								
								$output=$result;
						}
					}
					if($params[1]=="projects"){
						if(strlen($params[2])>0){
							
							$sql="SELECT tl_tasks.task_id,description, status FROM tl_tasks,tl_projects_tasks,tl_projects ";
							$sql.="WHERE tl_tasks.task_id=tl_projects_tasks.task_id ";
							$sql.="AND tl_projects.project_id=tl_projects_tasks.project_id ";
							$sql.="AND tl_projects.project_name=:project_name ";
							$sql.="AND tl_projects.user_id=:user_id";
							
							if(isset($url_query['show'])){
								$sql.=" AND status=:status";
							}
							
							
							$database->query($sql);
							$database->bind(":user_id",$user['u_id']);
							$database->bind(":project_name",$params[2]);
							if(isset($url_query['show'])){
								$database->bind(":status",$url_query['show']);
							}
							
							$result=$database->resultset();
							
							echo $database->error;
							foreach($result as &$item){
								//generate url
								$absolute_url = full_url( $_SERVER );
								$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."tasks/".$item['task_id'];
								$item['rel']=$absolute_url;
							}
							$output=$result;	
						}else{
							$database->query("SELECT project_id,project_name FROM tl_projects WHERE user_id=:user_id");
							$database->bind(":user_id",$user['u_id']);
							$result=$database->resultset();
							
							echo $database->error;
							foreach($result as &$item){
								$sql="SELECT COUNT(tl_tasks.task_id) as todos FROM tl_tasks,tl_projects_tasks WHERE tl_projects_tasks.task_id=tl_tasks.task_id AND tl_projects_tasks.project_id=:project_id AND status=:status";
								$database->query($sql);
								$database->bind(":project_id",$item['project_id']);
								$database->bind(":status","todo");
								$erg=$database->single();
								echo $database->error;
								$item['todos']=$erg['todos'];
								
								$database->query($sql);
								$database->bind(":project_id",$item['project_id']);
								$database->bind(":status","have-done");
								$erg=$database->single();
								echo $database->error;
								$item['havedones']=$erg['todos'];
								
								unset($item['project_id']);
								
								$absolute_url=full_url( $_SERVER );
								$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."projects/".$item['project_name'];
								$item['rel']=$absolute_url;
							}
							
							$output=$result;
						}
					}
				}
				else if($method=="DELETE"){
					$payload =json_decode(file_get_contents('php://input'));
					if(!is_array($payload)){
						http_response_code(400);
						die();
					}
					$database->beginTransaction();
					foreach($payload as $task){
						//check if user owns that task
						$database->query("SELECT task_id FROM tl_tasks,tl_users_tasks WHERE task_id=:task_id AND t_id=user_id AND user_id=:user_id");
						$database->bind(":task_id",$task);
						$database->bind(":user_id",$user['u_id']);
						$res=$database->single();
						$error=$database->error;
						
						if(is_numeric($res['task_id'])){
							$database->query("DELETE FROM tl_tasks WHERE task_id=:task_id");
							$database->bind(":task_id",$res['task_id']);
							$database->execute();
							$error.=$database->error;
							
							$database->query("DELETE FROM tl_tasks_history WHERE t_id=:task_id");
							$database->bind(":task_id",$res['task_id']);
							$database->execute();
							$error.=$database->error;
							
							$database->query("DELETE FROM tl_users_tasks WHERE t_id=:task_id");
							$database->bind(":task_id",$res['task_id']);
							$database->execute();
							$error.=$database->error;
							
							if($error!=""){
								$database->cancelTransaction();
								echo $error;
								http_response_code(400);
								die();
							}
						}else{
							$database->cancelTransaction();
							http_response_code(401);
							die();
						}
					}
					$database->endTransaction();
				}
				else if($method=="PUT"){
					$payload =json_decode(file_get_contents('php://input'));
					if(!is_array($payload)){
						http_response_code(400);
						die();
					}
					$database->beginTransaction();
					$newTasks=array();
					foreach($payload as $task){
						if(!is_numeric($task->task_id)){
							http_response_code(400);
							die();
						}
						//check if user owns that task
						$database->query("SELECT task_id FROM tl_tasks,tl_users_tasks WHERE task_id=:task_id AND t_id=user_id AND user_id=:user_id");
						$database->bind(":task_id",$task->task_id);
						$database->bind(":user_id",$user['u_id']);
						$res=$database->single();
						$error=$database->error;
						
						if(!is_numeric($res['task_id'])){
							$database->cancelTransaction();
							http_response_code(401);
							die();
						}
						
						//task desctiption cannot be empty
						$insert_array=array();
						
						if(isset($task->task_description)&&$task->task_description!=""){
							$insert_array["description"]=$task->task_description;
						}
						
						if(isset($task->urgent)){
							$insert_array["urgent"]=$task->urgent;
						}
						if(isset($task->important)){
							$insert_array['important']=$task->important;
						}
						if(isset($task->status)){
							$insert_array['status']=$task->status;
							
							$database->query("INSERT INTO tl_tasks_history (t_id,status,user_id,timestamp) VALUES (:t_id,:status,:user_id,:timestamp)");
							$database->bind(":t_id",$res['task_id']);
							$database->bind(":status","created");
							$database->bind(":user_id",$user['u_id']);
							$database->bind(":timestamp",time());
							$database->execute();
							$error.=$database->error;
						}
						if(isset($task->deadline)){
							$t=strtotime($task->deadline);
							$insert_array['deadline']=$t;
						}
						if(isset($task->repeat_interval)){
							$t=strtotime($task->repeat_interval);
							$diff=time();
							$t=t-$diff;
							$insert_array['repeat_interval']=$t;
						}
						if(isset($task->repeat_interval)){
							$t=strtotime($task->repeat_interval);
							if($t>0){
								$diff=time();
								$t=t-$diff;
							}
							$insert_array['repeat_interval']=$t;
						}
						if(isset($task->repeat_since)){
							$t=strtotime($task->repeat_since);
							$insert_array['repeat_since']=$t;
						}
						if(isset($task->repeat_until)){
							$t=strtotime($task->repeat_until);
							$insert_array['repeat_until']=$t;
						}
						
						$sql="UPDATE tl_tasks SET ";
								
						foreach($insert_array as $key=>$item){
							$sql.=$key."=:".$key.",";
						}
						$sql=substr($sql,0,strlen($sql)-1);
						$sql.=" WHERE task_id=:task_id";
												
						$database->query($sql);
						$database->bind(":task_id",$res['task_id']);
						
						foreach($insert_array as $key=>$item){
							$database->bind(":".$key,$item);
						}
						
						$database->execute();
						
						$error.=$database->error;
						
						if(is_array($task->projects)){
							
							$database->query("DELETE FROM tl_projects_tasks WHERE task_id=:task_id");
							$database->bind(":task_id",$res['task_id']);
							$database->execute();
							$error.=$database->error;
							
							foreach($task->projects as $proj){
								$database->query("SELECT project_id FROM tl_projects WHERE project_name=:project_name AND user_id=:user_id");
								$database->bind(":project_name",$proj);
								$database->bind(":user_id",$user['u_id']);
								$res2=$database->single();
								$error.=$database->error;
								if(!is_numeric($res2['project_id'])){
									$database->query("INSERT INTO tl_projects (project_name,user_id) VALUES (:project_name,:user_id)");
									$database->bind(":project_name",$proj);
									$database->bind(":user_id",$user['u_id']);
									$database->execute();
									$error.=$database->error;
									$res2['project_id']=$database->lastInsertId();
								}
								
								$database->query("INSERT INTO tl_projects_tasks (project_id,task_id) VALUES (:project_id,:task_id)");
								$database->bind(":project_id",$res2['project_id']);
								$database->bind(":task_id",$res['task_id']);
								$database->execute();
								$error.=$database->error;
							}
						}
						
						if($error!=""){
							$database->cancelTransaction();
							http_response_code(400);
							echo $error;
							die();
						}
						$newTasks[]=$res['task_id'];
					}	
					$database->endTransaction();
					
					//put out all new tasks
					$sql="SELECT task_id, description, status FROM tl_tasks WHERE task_id IN (:taskidstr)";
					$database->query($sql);
					$database->bind(":taskidstr",implode(",",$newTasks));
					$res=$database->resultset();
					
					foreach($res as &$item){
						//generate url
						$absolute_url = full_url( $_SERVER );
						$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."tasks/".$item['task_id'];
						$item['rel']=$absolute_url;
					}
					$output=$res;
					
				}
				else if($method=="POST"){
				$payload =json_decode(file_get_contents('php://input'));
				if(!is_array($payload)){
					http_response_code(400);
					die();
				}
				
				$database->beginTransaction();
				
				$newTasks=array();
				foreach($payload as $task){
					//check what is delivered
					if(!isset($task->task_description)){
						$database->cancelTransaction();
						http_response_code(400);
						die();
					}
					
					if($task->task_description==""){
						$database->cancelTransaction();
						http_response_code(400);
						die();
					}
					
					//1. insert the specific task
					$insert_array=array();
					$insert_array["description"]=$task->task_description;
					if(isset($task->urgent)){
						$insert_array["urgent"]=$task->urgent;
					}
					if(isset($task->important)){
						$insert_array['important']=$task->important;
					}
					if(isset($task->status)){
						$insert_array['status']=$task->status;
					}
					if(isset($task->deadline)){
						$t=strtotime($task->deadline);
						$insert_array['deadline']=$t;
					}
					if(isset($task->repeat_interval)){
						$t=strtotime($task->repeat_interval);
						$diff=time();
						$t=t-$diff;
						$insert_array['repeat_interval']=$t;
					}
					if(isset($task->repeat_interval)){
						$t=strtotime($task->repeat_interval);
						if($t>0){
							$diff=time();
							$t=t-$diff;
						}
						$insert_array['repeat_interval']=$t;
					}
					if(isset($task->repeat_since)){
						$t=strtotime($task->repeat_since);
						$insert_array['repeat_since']=$t;
					}
					if(isset($task->repeat_until)){
						$t=strtotime($task->repeat_until);
						$insert_array['repeat_until']=$t;
					}
					
					$keys=implode(",",array_keys($insert_array));
					$vals_masked=":".str_replace(",",",:",$keys);
					
					$sql="INSERT INTO tl_tasks (".$keys.") VALUES (".$vals_masked.")";
					$database->query($sql);
					
					foreach($insert_array as $key=>$item){
						$database->bind(":".$key,$item);
					}
					
					$database->execute();
					
					$taskid=$database->lastInsertId();
					$newTasks[]=$taskid;
					$error=$database->error;
				
					$sql="INSERT INTO tl_users_tasks (t_id,user_id) VALUES (".$taskid.",".$user['u_id'].")";
					$database->query($sql);
					$database->execute();
					
					$error.=$database->error;
					
					if(is_array($task->projects)){
						foreach($task->projects as $proj){
							$database->query("SELECT project_id FROM tl_projects WHERE project_name=:project_name AND user_id=:user_id");
							$database->bind(":project_name",$proj);
							$database->bind(":user_id",$user['u_id']);
							$res=$database->single();
							$error.=$database->error;
							if(!is_numeric($res['project_id'])){
								$database->query("INSERT INTO tl_projects (project_name,user_id) VALUES (:project_name,:user_id)");
								$database->bind(":project_name",$proj);
								$database->bind(":user_id",$user['u_id']);
								$database->execute();
								$error.=$database->error;
								$res['project_id']=$database->lastInsertId();
							}
							
							$database->query("INSERT INTO tl_projects_tasks (project_id,task_id) VALUES (:project_id,:task_id)");
							$database->bind(":project_id",$res['project_id']);
							$database->bind(":task_id",$taskid);
							$database->execute();
							$error.=$database->error;
						}
					}
					//4. insert into history
					$database->query("INSERT INTO tl_tasks_history (t_id,status,user_id,timestamp) VALUES (:t_id,:status,:user_id,:timestamp)");
					$database->bind(":t_id",$taskid);
					$database->bind(":status","created");
					$database->bind(":user_id",$user['u_id']);
					$database->bind(":timestamp",time());
					$database->execute();
					$error.=$database->error;
					
					
					if($error!=""){
					$database->cancelTransaction();
					http_response_code(400);
					echo $error;
					die();
					}
				}
				$database->endTransaction();
				
				//put out all new tasks
				$sql="SELECT task_id, description, status FROM tl_tasks WHERE task_id IN (:taskidstr)";
				$database->query($sql);
				$database->bind(":taskidstr",implode(",",$newTasks));
				$res=$database->resultset();
				
				
				foreach($res as &$item){
					//generate url
					$absolute_url = full_url( $_SERVER );
					$absolute_url=substr($absolute_url,0,strpos($absolute_url,$user['u_name'])).$user['u_name']."/"."tasks/".$item['task_id'];
					$item['rel']=$absolute_url;
				}
				$output=$res;
				http_response_code(201);
			}
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
	
	echo $output;
	die();
	
}

if(count($output)>0){
header('Content-Type: application/json');
echo json_encode($output);
}
else http_response_code(200);

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

function is_valid_user($user){
	if(isset(Installer::sharedInstaller()->conf['basic_auth_pw']))
		if(Installer::sharedInstaller()->conf['uses_basic_auth']==1)
			return ($user['u_name']==$_SERVER['PHP_AUTH_USER']&&password_verify($_SERVER['PHP_AUTH_PW'],$user['u_password']));
	return true;	
}


function url_origin( $s, $use_forwarded_host = false )
{
	$ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
	$sp       = strtolower( $s['SERVER_PROTOCOL'] );
	$protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
	$port     = $s['SERVER_PORT'];
	$port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
	$host     = ( $use_forwarded_host && isset( $s['HTTP_X_FORWARDED_HOST'] ) ) ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
	$host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
	return $protocol . '://' . $host;
}

function full_url( $s, $use_forwarded_host = false )
{
	return url_origin( $s, $use_forwarded_host ) . $s['REQUEST_URI'];
}

?>