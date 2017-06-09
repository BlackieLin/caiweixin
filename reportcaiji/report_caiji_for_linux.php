<?php
/**
 * @author: linhai6776@163.com
 */
set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);
header("Content-type: text/html; charset=utf-8"); 
require_once("bin/Mysql.class.php");
require_once("bin/Utility.class.php");

$ing_file=dirname(__FILE__)."/ing.txt";//这个防止多个进程同时采集
file_put_contents($ing_file,"采集进行中，占住进程...");	

//只录入有appid和secret的公众号 ==== 取消自动录入数据，请不要填写appid和secret数据
$reportGzh=array();
//$sql='SELECT gzh_id,gzh_name,appid,secret FROM `weixin_report_gzh` where appid!="" and secret!="" and appid="wxc751bc36bc2adcf9"';
$sql='SELECT gzh_id,gzh_name,appid,secret FROM `weixin_report_gzh` where appid!="" and secret!="" ';
$reportGzh=Mysql::obj()->fetch_row($sql,false);
if(!empty($reportGzh)){
	foreach($reportGzh as $key=>$val){
		//获取会话token
		$access_token="";
		$access_token=get_access_token($val['appid'],$val['secret']);
		if($access_token){
			$org_start_caiji_date=$start_caiji_date=date('Ymd',mktime(0,0,0,date("m"),date("j")-9,date("Y")));//默认采集前一个星期的数据,id_no
			$end_caiji_date=date('Ymd',mktime(0,0,0,date("m"),date("j")-1,date("Y")));//最多也只能采集昨天的数据
			$topGzhAnalysis=array();
			$topGzhAnalysis=Mysql::obj()->fetch_row('SELECT id,id_no FROM `weixin_report_analysis` where gzh_id='.$val['gzh_id'].' order by id_no desc');
			if(!empty($topGzhAnalysis)) $start_caiji_date=$topGzhAnalysis['id_no'];
			//如果数据库里面的数据已经是最大能采集的数据啦
			/*if($start_caiji_date==$end_caiji_date){
				echoWrite("======“".$val['gzh_name']."”的数据已经是最新数据,跳出不需要采集=======");	
				continue;
			}*/
			$start_caiji_date = $org_start_caiji_date<$start_caiji_date ? $org_start_caiji_date:$start_caiji_date;//默认采集一个星期收据，有就更新，没有就添加
			//开始获取数据
			$cur_caiji_date=strtotime($start_caiji_date)+3600*24;//当前录入数据时间戳
			while($cur_caiji_date<=strtotime($end_caiji_date)){
				
				$tempDate=date("Y-m-d",$cur_caiji_date);
				
				//获取数据API
				$analysis=$analysis_user=$analysis_yuedu=array();
				$analysis_user=get_analysis_user($access_token,$tempDate);
				$analysis_yuedu=get_analysis_yuedu($access_token,$tempDate);
				$analysis=array_merge($analysis_user,$analysis_yuedu);
				
				//=============
				//print_r($analysis);
				//=============
				
				if(!empty($analysis)&&$analysis['total_fans']){//有总粉丝数才录入
				//if(!empty($analysis)){
					$data=array();
					$data['gzh_id']=$val['gzh_id'];
					$data['total_fans']=$analysis['total_fans'];
					$data['new_fans']=$analysis['new_fans'];
					$data['old_fans']=$analysis['old_fans'];
					$data['total_read']=$analysis['total_read'];
					$data['user_dialog']=$analysis['user_dialog'];
					$data['user_wechat']=$analysis['user_wechat'];
					$data['user_share']=$analysis['user_share'];
					$data['top_line']=$analysis['top_line'];
					$data['public_num']=$analysis['public_num'];
					$data['opt_time']=$cur_caiji_date;
					$data['id_no']=date('Ymd',$cur_caiji_date);
					insert_analysis_database($data);
				}else{
					echoWrite("======获取不到公众号：“".$val['gzh_name']."”，日期为：“".$tempDate."”的数据=======");		
				}
				$cur_caiji_date=$cur_caiji_date+3600*24;
				
			}
			echoWrite("======“".$val['gzh_name']."”的数据已经采集完成=======");	
		}else{
			echoWrite("======公众号：“".$val['gzh_name']."的”access_token获取不到数据录入为：“".$val['appid']."”--“".$val['secret']."”=======");		
		}
	}
}else{
	echoWrite("======没有需要采集的公众号=======");	
}
unlink($ing_file);//删除占进程文件...
// ================================== 以下是常用方法 =============================

//插入数据库的函数
function insert_analysis_database($data){
	
	$temp_analysis_data=array();//如果该公众号已经录入数据，就修改
	$temp_analysis_data=Mysql::obj()->fetch_row('SELECT id FROM `weixin_report_analysis` where `gzh_id`='.$data['gzh_id'].' and id_no='.$data['id_no']);
	if(!empty($temp_analysis_data)&&$temp_analysis_data['id']){
		Mysql::obj()->update("UPDATE `weixin_report_analysis` SET `gzh_id` = ".$data['gzh_id'].",`total_fans` = ".$data['total_fans'].",`new_fans` = ".$data['new_fans'].",`old_fans` = ".$data['old_fans'].",`total_read` = ".$data['total_read'].",`user_dialog` = ".$data['user_dialog'].",`user_wechat` = ".$data['user_wechat'].",`user_share` = ".$data['user_share'].",`top_line` = ".$data['top_line'].",`public_num` = ".$data['public_num'].",`opt_time` = ".$data['opt_time'].",`id_no` = ".$data['id_no']." WHERE `id` = ".$temp_analysis_data['id']);//更新阅读数
		echoWrite("======公众号id:“".$data['gzh_id']."”更新完成,更新ID：“".$temp_analysis_data['id']."”====录入的数据为：浏览量：“".print_r($data,true)."”===");	
	}else{
		$data_id=0;
		$sql="INSERT INTO `weixin_report_analysis` ( `gzh_id` , `total_fans` , `new_fans` , `old_fans` , `total_read` , `user_dialog` , `user_wechat` , `user_share` , `top_line` , `public_num` , `opt_time` , `id_no` ) VALUES ( ".$data['gzh_id']." , ".$data['total_fans']." , ".$data['new_fans']." , ".$data['old_fans']." , ".$data['total_read']." , ".$data['user_dialog']." , ".$data['user_wechat']." , ".$data['user_share']." , ".$data['top_line']." , ".$data['public_num']." , ".$data['opt_time']." , ".$data['id_no']." )";
		$data_id=Mysql::obj()->insert($sql);//插入内容
		echoWrite("======公众号id:“".$data['gzh_id']."”采集完成,返回值为ID：“".$data_id."”====录入的数据为：“".print_r($data,true)."”===");	
	}
}

//获取会话token
function get_access_token($appid,$secret){
	$jsonContent=$access_token="";
	$result=array();
	$apiUrl='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$appid.'&secret='.$secret;
	$jsonContent=http_get($apiUrl);
	$result=json_decode($jsonContent,true);
	if(!empty($result)&&isset($result['access_token'])){//成功
		$access_token=$result['access_token'];
	}else{
		echoWrite("接口请求失败“".$result['errmsg']."”！地址：".$apiUrl);	
	}
	return $access_token;
}

//获取用户数据，默认获取一天的数据
function get_analysis_user($access_token,$date=''){
	$date=$date?$date:date('Y-m-d',mktime(0,0,0,date("m"),date("j")-1,date("Y")));
	$json_req='{"begin_date":"'.$date.'", "end_date":"'.$date.'"}';
	$result_sum=$result_cum=array();
	$result=array(
		'new_fans'=>0,
		'old_fans'=>0,
		'total_fans'=>0
	);
	$jsonContent="";
	$apiUrl_getusersummary='https://api.weixin.qq.com/datacube/getusersummary?access_token='.$access_token;//获取用户增减数据
	$apiUrl_getusercumulate='https://api.weixin.qq.com/datacube/getusercumulate?access_token='.$access_token;//获取累计用户数据
	//获取新增和取关数
	$jsonContent=do_post_request($apiUrl_getusersummary,$json_req);
	$result_sum=json_decode($jsonContent,true);
	if(!empty($result_sum)&&isset($result_sum['list'])&&!empty($result_sum['list'])){
		$new_user=$cancel_user=0;
		foreach($result_sum['list'] as $key=>$val){
			$new_user=$new_user+$val['new_user'];
			$cancel_user=$cancel_user+$val['cancel_user'];
		}
		$result['new_fans']=$new_user;//新增关注
		$result['old_fans']=$cancel_user;//取消关注
	}else{
		echoWrite("接口请求失败“".$jsonContent."”！地址：".$apiUrl_getusersummary.'&json='.$json_req);	
	}
	//获取总粉丝数
	$jsonContent=do_post_request($apiUrl_getusercumulate,$json_req);
	$result_cum=json_decode($jsonContent,true);
	if(!empty($result_cum)&&isset($result_cum['list'])&&!empty($result_cum['list'])){
		$cumulate_user=0;
		foreach($result_cum['list'] as $key=>$val){
			$cumulate_user=$val['cumulate_user'];
		}
		$result['total_fans']=$cumulate_user;//新增关注
	}else{
		echoWrite("接口请求失败“".$jsonContent."”！地址：".$apiUrl_getusercumulate.'&json='.$json_req);		
	}
	return $result;
}

//获取阅读数据，默认获取一天的数据
function get_analysis_yuedu($access_token,$date=''){
	$date=$date?$date:date('Y-m-d',mktime(0,0,0,date("m"),date("j")-1,date("Y")));
	$json_req='{"begin_date":"'.$date.'", "end_date":"'.$date.'"}';
	$result_sum=$result_cum=array();
	$result=array(
		'total_read'=>0,
		'user_dialog'=>0,
		'public_num'=>0,
		'top_line'=>0,
		'user_share'=>0,
		'user_wechat'=>0
	);
	$jsonContent="";
	$apiUrl_getuserread='https://api.weixin.qq.com/datacube/getuserread?access_token='.$access_token;//获取图文统计数据
	$apiUrl_getarticletotal='https://api.weixin.qq.com/datacube/getarticletotal?access_token='.$access_token;//获取图文群发总数据
	//获取图文统计数据,总阅读,会话，朋友圈
	$jsonContent=do_post_request($apiUrl_getuserread,$json_req);
	$result_sum=json_decode($jsonContent,true);
	if(!empty($result_sum)&&isset($result_sum['list'])&&!empty($result_sum['list'])){
		$total_read=$user_dialog=$user_wechat=$total_share=0;
		foreach($result_sum['list'] as $key=>$val){
			$total_read=$total_read+$val['int_page_read_user'];
			if($val['user_source']==0){//会话
				$user_dialog=$val['int_page_read_user'];
			}
			if($val['user_source']==2){//朋友圈
				$user_wechat=$val['int_page_read_user'];
			}
			$total_share=$total_share+$val['share_user'];
		}
		$result['total_read']=$total_read;//总阅读
		$result['user_dialog']=$user_dialog;//会话数
		$result['user_wechat']=$user_wechat;//朋友圈数
		$result['user_share']=$total_share;//分享数
	}else{
		echoWrite("接口请求失败“".$jsonContent."”！地址：".$apiUrl_getuserread.'&json='.$json_req);	
	}
	//获取头条阅读和发布次数
	$jsonContent=do_post_request($apiUrl_getarticletotal,$json_req);
	$result_cum=json_decode($jsonContent,true);
	if(!empty($result_cum)&&isset($result_cum['list'])&&!empty($result_cum['list'])){
		$public_num=$top_line=0;
		foreach($result_cum['list'] as $key=>$val){
			$lastArr=array();
			if($key==0){
				$lastArr=end($val['details']);
				$top_line=$lastArr['int_page_read_user'];
			}
		}
		$result['public_num']=count($result_cum['list']);//发布条数
		$result['top_line']=$top_line;//头条阅读数
	}else{
		echoWrite("接口请求失败“".$jsonContent."”！地址：".$apiUrl_getarticletotal.'&json='.$json_req);		
	}
	return $result;
}

//公共方法=========================

//日志函数
function echoWrite($msg){
	$msg=$msg.";".date("Y-m-d H:i:s",time())."\r\n";//美观需要
	echo $msg;
	file_put_contents(dirname(__FILE__)."/log/".date("Y-m-d")."_log.txt",$msg,FILE_APPEND);
}

//post请求函数
function do_post_request($url, $data, $optional_headers = null){
	 $params = array('http' => array(
				  'method' => 'POST',
				  'content' => $data
			   ));
	 if ($optional_headers !== null) {
		$params['http']['header'] = $optional_headers;
	 }
	 $ctx = stream_context_create($params);
	 $fp = @fopen($url, 'rb', false, $ctx);
	 if (!$fp) {
		throw new Exception("Problem with $url, $php_errormsg");
	 }
	 $response = @stream_get_contents($fp);
	 if ($response === false) {
		throw new Exception("Problem reading data from $url, $php_errormsg");
	 }
	 return $response;
}

function http_get($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
}

?>