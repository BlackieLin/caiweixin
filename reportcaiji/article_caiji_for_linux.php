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
$lastUpdateTime_new=@filemtime($ing_file);
$stop_now=time();
if(($stop_now-$lastUpdateTime_new)>6*3600){//如果文件存在超过6小时占用进程，就清除占进程文件
	if(is_file($ing_file)){
		unlink($ing_file);//删除占进程文件
	}
}
if(is_file($ing_file)){//如果已经在采集了，就直接die掉
	echoWrite("队列中存在采集程序，必须先等一半时才能继续采集，报错时间：".date("Y-m-d H:i:s",$stop_now));
	sleep(1200);//延迟一个小时执行
}
if(is_file($ing_file)){//如果已经在采集了，就直接die掉
	$stop_now=time();
	echoWrite("队列中存在采集程序，必须先再等一个小时才能继续采集，报错时间：".date("Y-m-d H:i:s",$stop_now));
	sleep(3600);//延迟一个小时执行
}
if(is_file($ing_file)){//如果已经在采集了，就直接die掉
	$stop_now=time();
	echoWrite("队列中存在采集程序，必须先再====再等一个小时才能继续采集，报错时间：".date("Y-m-d H:i:s",$stop_now));
	sleep(3600);//延迟一个小时执行
}

//只录入有appid和secret的公众号 ==== 取消自动录入数据，请不要填写appid和secret数据
$reportGzh=array();
//$sql='SELECT gzh_id,gzh_name,appid,secret FROM `weixin_report_gzh` where appid!="" and secret!="" and appid="wx9ce5ac35aa261989"';//深圳本地宝
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
			$topGzhAnalysis=Mysql::obj()->fetch_row('SELECT art_id,art_pubtime FROM `weixin_report_article` where gzh_id='.$val['gzh_id'].' order by art_pubtime desc');
			if(!empty($topGzhAnalysis)) $start_caiji_date=$topGzhAnalysis['art_pubtime'];
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
				
				$allFans=0;
				$allFans=get_all_user($access_token,$tempDate);//获取当天总粉丝数
				if($allFans>=1000){//有总粉丝数大于1000才录入
					//获取数据API
					$article=array();
					$article=get_article_yuedu($access_token,$tempDate);
					if(!empty($article)){
						foreach($article as $k=>$v){
							//if($v['art_view']/$allFans>=0.15){// 阅读/粉丝数>=15% 符合录入标准
								$data=array();
								$data['gzh_id']=$val['gzh_id'];
								$data['art_title']=$v['art_title'];
								$data['art_url']=$v['art_url'];
								$data['art_view']=$v['art_view'];
								$data['art_share']=$v['art_share'];
								$data['art_pubtime']=$v['art_pubtime'];
								$data['art_note']=$v['art_note'];
								insert_analysis_database($data);
							//}else{
								//echoWrite("======“".$val['gzh_name']."”的“".$v['art_title']."”文章不符合要求".$v['art_view']."/".$allFans."《15%====日期为：“".$tempDate."”的数据===");	
								//continue;	
							//}
						}
					}else{
						echoWrite("======获取不到公众号：“".$val['gzh_name']."”，日期为：“".$tempDate."”的数据");
					}
				}else{
					echoWrite("======公众号：“".$val['gzh_name']."”，日期为：“".$tempDate."”粉丝数少于1000当前粉丝数为：“".$allFans."”=======");		
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
unlink($ing_file);//删除占进程文件...虽然没有什么意义，但是为防止永久不采集时用
// ================================== 以下是常用方法 =============================

//获取微信文章
function get_article_yuedu($access_token,$date=''){
	$date=$date?$date:date('Y-m-d',mktime(0,0,0,date("m"),date("j")-1,date("Y")));
	$json_req='{"begin_date":"'.$date.'", "end_date":"'.$date.'"}';
	$result=$result_sum=$result_cum=array();
	$jsonContent="";
	$apiUrl_getarticletotal='https://api.weixin.qq.com/datacube/getarticletotal?access_token='.$access_token;//获取图文群发总数据
	//获取图文统计数据,总阅读,会话，朋友圈
	$jsonContent=do_post_request($apiUrl_getarticletotal,$json_req);
	$result_cum=json_decode($jsonContent,true);
	if(!empty($result_cum)&&isset($result_cum['list'])&&!empty($result_cum['list'])){
		$public_num=$top_line=0;
		foreach($result_cum['list'] as $key=>$val){
			$result[$key]['art_title']=$val['title'];
			$result[$key]['art_url']='http://weixin.sogou.com/weixin?type=2&query='.urlencode($val['title']);
			$lastArr=array();
			$lastArr=end($val['details']);
			$result[$key]['art_view']=$lastArr['int_page_read_user'];
			$result[$key]['art_share']=$lastArr['share_user'];
			$result[$key]['art_pubtime']=str_replace("-","",$val['ref_date']);
			$result[$key]['art_note']='';
		}
	}else{
		echoWrite("接口请求失败“".$jsonContent."”！地址：".$apiUrl_getarticletotal.'&json='.$json_req);		
	}
	return $result;
}

//插入数据库的函数
function insert_analysis_database($data){
	$art_result=array('art_id'=>0);
	$art_result=Mysql::obj()->fetch_row('SELECT art_id FROM `weixin_report_article` where gzh_id='.$data['gzh_id'].' and art_pubtime='.$data['art_pubtime'].' and art_title="'.$data['art_title'].'"');
	if(!empty($art_result)&&$art_result['art_id']){
		Mysql::obj()->update("UPDATE `weixin_report_article` SET `art_view` = '".$data['art_view']."',`art_share` = '".$data['art_share']."' WHERE `art_id` = ".$art_result['art_id']);//更新阅读数
		echoWrite("======公众号id:“".$data['gzh_id']."”更新完成,返回值为ID：“".$art_result['art_id']."”====录入的数据为：浏览量：“".$data['art_view']."”===");	
	}else{
		$data_id=0;
		$sql="INSERT INTO `weixin_report_article` ( `gzh_id` , `art_title` , `art_url` , `art_view` , `art_share` , `art_pubtime` , `art_note` ) VALUES ( ".$data['gzh_id']." , '".$data['art_title']."' , '".$data['art_url']."' , ".$data['art_view']." , ".$data['art_share']." , ".$data['art_pubtime']." , '".$data['art_note']."' )";
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

//获取总粉丝数目
function get_all_user($access_token,$date=''){
	$date=$date?$date:date('Y-m-d',mktime(0,0,0,date("m"),date("j")-1,date("Y")));
	$json_req='{"begin_date":"'.$date.'", "end_date":"'.$date.'"}';
	$result_sum=$result_cum=array();
	$result=array(
		'total_fans'=>0
	);
	$jsonContent="";
	$apiUrl_getusercumulate='https://api.weixin.qq.com/datacube/getusercumulate?access_token='.$access_token;//获取累计用户数据
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
	return $result['total_fans'];
}

//公共方法=========================

//日志函数
function echoWrite($msg){
	$msg=$msg.";".date("Y-m-d H:i:s",time())."\r\n";//美观需要
	echo $msg;
	file_put_contents(dirname(__FILE__)."/log/".date("Y-m-d")."_art_log.txt",$msg,FILE_APPEND);
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
		//throw new Exception("Problem with $url, $php_errormsg");
		echoWrite("Problem with $url, $php_errormsg");
	 }
	 $response = @stream_get_contents($fp);
	 if ($response === false) {
		//throw new Exception("Problem reading data from $url, $php_errormsg");
		echoWrite("Problem reading data from $url, $php_errormsg");
	 }
	 return $response;
}
//post请求函数2
function do_post_request2($url, $data, $optional_headers = null){
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_HTTPHEADER, array(
               'Content-Type: application/json',
               'Content-Length: ' . strlen($data))
      );
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
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