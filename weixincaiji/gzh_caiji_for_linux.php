<?php 
/**
 * @author: linhai6776@163.com
 */
set_time_limit(0);
error_reporting(E_ALL ^ E_NOTICE);
header("Content-type: text/html; charset=utf-8"); 
require_once("bin/Mysql.class.php");
require_once("bin/Utility.class.php");

$stop_now=time();
$stop_start=mktime(0,0,0,date("m"),date("j"),date("Y"));
$stop_end=mktime(8,5,0,date("m"),date("j"),date("Y"));

/*if(($stop_start<$stop_now&&$stop_now<$stop_end)||(mktime(9,50,0,date("m"),date("j"),date("Y"))<$stop_now&&$stop_now<mktime(14,0,0,date("m"),date("j"),date("Y")))||(mktime(14,50,0,date("m"),date("j"),date("Y"))<$stop_now&&$stop_now<mktime(20,0,0,date("m"),date("j"),date("Y")))){//0-8,10-13.59,15-19.59点不执行 9,14,20,21,22,23
	die("0-8,10-13.59,15-19.59 not work time");
}*/

$stop_start_3 = mktime(3,0,0,date("m"),date("j"),date("Y"));
$stop_end_6 = mktime(6,0,0,date("m"),date("j"),date("Y"));
if(($stop_start_3<$stop_now&&$stop_now<$stop_end_6)){//0-8点不执行,每隔30分钟执行一次
	die("3-6 not work time");
}


$ing_file=dirname(__FILE__)."/ing.txt";//这个防止多个进程同时采集
$lastUpdateTime_new=@filemtime($ing_file);

if(mktime(8,50,0,date("m"),date("j"),date("Y"))<$stop_now&&$stop_now<mktime(9,10,0,date("m"),date("j"),date("Y"))){//如果是第二天早上九点，强制删除占进程文件，避免程序执行宕机了，未执行完，一直不执行的情况！
	if(is_file($ing_file)){
		unlink($ing_file);//删除占进程文件...8：50-9：10执行
	}
}else if(($stop_now-$lastUpdateTime_new)>10*3600){//如果文件存在超过10小时占用进程，就清除占进程文件
	if(is_file($ing_file)){
		unlink($ing_file);//删除占进程文件
	}
}
if(is_file($ing_file)){//如果已经在采集了，就直接die掉
	echoWrite("队列中存在采集程序，必须先终止上一个队列才能继续采集，报错时间：".date("Y-m-d H:i:s",$stop_now));
	die("have ing php");
}else{//没有进程，先占据进程=====
	file_put_contents($ing_file,"采集进行中，占住进程...");	
}



$result=array();
$sql="SELECT gzh.id,gzh.siteid,gzh.classid,gzh.account,gzh.state,gzh.last_caiji_time,gzh.nickname,site.checked FROM `weixin_gzh` as gzh left join site as site on gzh.siteid=site.id WHERE off_state=1 ORDER BY gzh.`last_caiji_time` ASC,gzh.`id` ASC limit 0,400";//state没多大用,必须在采集队列里，才采集,上次采集时间越久越先采集
$result=Mysql::obj()->fetch_row($sql,false);

$count_302=0;

//循环所有公众号
foreach($result as $gzh_key=>$gzh_val){
	
	//每天只能采集一次，只要过了零点就可以采集一次
	$lastUpdateTime=$gzh_val['last_caiji_time'];
	$tody_caiji=true;//默认今天可以采集
	$caijiSucess=true;
	$now=time();
	//if($now<mktime(23,59,59,date("m",$lastUpdateTime),date("j",$lastUpdateTime),date("Y",$lastUpdateTime))){//如果今天更新了，就不更新了
	//	$tody_caiji=false;
	//}
	//如果今天没采集，才让它采集
	if($tody_caiji){
	
	
		//获取公众号搜索地址
		$sougou_search_gzh_url='http://weixin.sogou.com/weixin?type=1&query='.$gzh_val['account'].'&ie=utf8&_sug_=n&_sug_type_=';;//工作好账号采集不准确
		//$nickname_gbk = mb_convert_encoding($gzh_val['nickname'], "gbk", "UTF-8");
		$nickname_gbk = $gzh_val['nickname'];
		$sougou_search_gzh_url='http://weixin.sogou.com/weixin?type=1&query='.urlencode(trim($nickname_gbk)).'&ie=utf8&_sug_=n&_sug_type_=';;//换成公众号名称采集更准确
		
		//获取实时的微信公众号文章列表
		$content=$url="";
		$matches=$matches_account=$matches_s=array();
		$content=Utility::HttpRequest($sougou_search_gzh_url);// die();
		if(strpos($content,"302 Found")!==false){//如果页面是302
			make_cookie_file();
			$content=Utility::HttpRequest($sougou_search_gzh_url);
		}
		//preg_match_all('/\<div target=\"_blank\" href=\"(.+)"/Uis',$content,$matches);
		//preg_match_all('/\<label name=\"em_weixinhao\">(.+)\<\/label\>/Uis',$content,$matches_account);//确保搜索的公众号账号和名称对应
		preg_match_all('/\<a target=\"_blank\" uigs=\"account_name_(\d)\" href=\"(.+)"/Uis',$content,$matches_s);//$matches[2]应
		preg_match_all('/\<label name=\"em_weixinhao\">(.+)\<\/label\>/Uis',$content,$matches_account);
		if(!empty($matches_account)&&!empty($matches_account[1])&&!empty($matches_s)&&!empty($matches_s[2])){
			$matches[1]=$matches_s[2];//利用旧元素
			foreach($matches_account[1] as $a_key=>$a_val){
				if($gzh_val['account']==$a_val){//确保数据库的公众号和查询的公众对应
					$url=str_replace("&amp;","&",$matches[1][$a_key]);//最终获得的地址，能确保不错
				}
			}
		}
		//如果第一页未找到，去第二页找 已经没有第二页了
		if($url==""){
			$content=$url="";
			$matches=$matches_account=array();
			//$sougou_search_gzh_url='http://weixin.sogou.com/weixin?type=1&query='.urlencode(trim($nickname_gbk)).'&ie=utf8&page=2&_sug_=n&_sug_type_=';
			$sougou_search_gzh_url='http://weixin.sogou.com/weixin?type=1&query='.$gzh_val['account'].'&ie=utf8&_sug_=n&_sug_type_=';
			$content=Utility::HttpRequest($sougou_search_gzh_url);
			//preg_match_all('/\<div target=\"_blank\" href=\"(.+)"/Uis',$content,$matches);
			//preg_match_all('/\<label name=\"em_weixinhao\">(.+)\<\/label\>/Uis',$content,$matches_account);//确保搜索的公众号账号和名称对应
			//if(!empty($matches_account)&&!empty($matches_account[1])&&!empty($matches)&&!empty($matches[1])){
			preg_match_all('/\<a target=\"_blank\" uigs=\"account_name_(\d)\" href=\"(.+)"/Uis',$content,$matches_s);//$matches[2]应
			preg_match_all('/\<label name=\"em_weixinhao\">(.+)\<\/label\>/Uis',$content,$matches_account);
			if(!empty($matches_account)&&!empty($matches_account[1])&&!empty($matches_s)&&!empty($matches_s[2])){
				$matches[1]=$matches_s[2];//利用旧元素
				foreach($matches_account[1] as $a_key=>$a_val){
					if($gzh_val['account']==$a_val){//确保数据库的公众号和查询的公众对应
						$url=str_replace("&amp;","&",$matches[1][$a_key]);//最终获得的地址，能确保不错
					}
				}
			}
		}//end
		
		if($url==""){//没有获取到URL的时候，报错
			echoWrite("未获取到公众号地址,尝试手动访问：".$sougou_search_gzh_url.";需重后台手动采集该公众号：".$gzh_val['account']);
			if(strpos($content,"302 Found")!==false){//如果页面是302
				
				echoWrite("-------302页面，禁止访问！-----------");
				$count_302++;
				//暂时去掉停止，因为有多个服务器，不受影响
				if($count_302>10){//当连续出现多次302的时候，就停掉此次采集，等待下一次采集
					if(is_file($ing_file)){
						unlink($ing_file);//删除占进程文件...8：50-9：10执行
					}
					echoWrite("-------302页面过多，禁止访问，那么就停止此次采集！并删除占进程文件！-----------");
					die(302);
				}
			}else{
				$count_302=0;	
			}
		}
		
		//开始采集、下载远程图片、保存到数据库
		$content="";
		$matches=$listArr=array();
		$content=Utility::HttpRequest($url,$listArr,$sougou_search_gzh_url);
		//preg_match_all('/var msgList = \'(.+)\'/Uis',$content,$matches);
		preg_match_all('/var msgList = (.+) seajs.use/Uis',$content,$matches);
		if(!empty($matches)&&!empty($matches[1])&&isset($matches[1][0])){
			$jsonStr="";
			//$jsonStr=str_replace(array('&quot;','&amp;'),array('"',"&"),$matches[1][0]);
			$jsonStr=str_replace(array('&amp;','}}]};'),array("&",'}}]}'),$matches[1][0]);
			$listArr=json_decode(stripslashes($jsonStr),true);//所有列表数据
			if(!empty($listArr)&&!empty($listArr['list'])){
				$data=array();
				$key_i=0;
				if($gzh_val['siteid']&&$gzh_val['classid']){
					foreach($listArr['list'] as $key=>$val){
						//获取时间
						$timestamp=0;
						$timestamp=$val['comm_msg_info']['datetime'];//每次发布只有一个时间
						//下载远程图片并保存数据，数据分两层结构
						$data[$key_i]['gzh_id']=$gzh_val['id'];
						$data[$key_i]['siteid']=$gzh_val['siteid'];
						$data[$key_i]['classid']=$gzh_val['classid'];
						$data[$key_i]['checked']=$gzh_val['checked'];
						$data[$key_i]['timestamp']=$timestamp;
						$data[$key_i]['title']=trim($val['app_msg_ext_info']['title']);
						$data[$key_i]['title']=preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', str_replace(array("\"","'"),array("”","’"),stripslashes($data[$key_i]['title'])));//直接过滤utf8mb4字符
						$data[$key_i]['digest']=trim($val['app_msg_ext_info']['digest']);
						if(!data_exit($data[$key_i]['siteid'],$data[$key_i]['title'])&&$data[$key_i]['title']){//不采集标题相同的
							$data[$key_i]['cover']=download_img(trim($val['app_msg_ext_info']['cover']));
							$data[$key_i]['content']=get_content_by_url(trim($val['app_msg_ext_info']['content_url']));
							list($data[$key_i]['sg_hit'],$data[$key_i]['sg_zan'])=get_comment_by_url(trim($val['app_msg_ext_info']['content_url']));//赞和阅读
							//处理过的数据
							if($data[$key_i]['content']){//有内容才采集
								$tempData=array();
								$tempData=$data[$key_i];
								$info_id=0;
								list($info_id,$code,$msg)=caiji_to_qianjiapu_db($tempData);
								if($info_id){
									echoWrite($data[$key_i]['title']."==该条信息采集成功，msg:“".$msg."”，公众号：".$gzh_val['account']);	
								}else{
									echoWrite($data[$key_i]['title']."==该条信息采集失败，msg:“".$msg."”，公众号：".$gzh_val['account']);		
								}
							}else{
								echoWrite($data[$key_i]['title']."==该条信息采集失败，msg:“信息内容为空”，公众号：".$gzh_val['account']);		
							}
							
						}else{
							echoWrite($data[$key_i]['title']."==已经存在，或者标题内容为空，公众号：".$gzh_val['account']);	
						}
						if(is_array($val['app_msg_ext_info']['multi_app_msg_item_list'])){
							krsort($val['app_msg_ext_info']['multi_app_msg_item_list']);//倒序插入
							foreach($val['app_msg_ext_info']['multi_app_msg_item_list'] as $k=>$v){
								$data[$key_i+1]['gzh_id']=$gzh_val['id'];
								$data[$key_i+1]['siteid']=$gzh_val['siteid'];
								$data[$key_i+1]['classid']=$gzh_val['classid'];
								$data[$key_i+1]['checked']=$gzh_val['checked'];
								$data[$key_i+1]['timestamp']=$timestamp;
								$data[$key_i+1]['title']=trim($v['title']);
								$data[$key_i+1]['title']=preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', str_replace(array("\"","'"),array("”","’"),stripslashes($data[$key_i+1]['title'])));//直接过滤utf8mb4字符
								$data[$key_i+1]['digest']=trim($v['digest']);
								if(!data_exit($data[$key_i+1]['siteid'],$data[$key_i+1]['title'])&&$data[$key_i+1]['title']){//不采集标题相同的
									$data[$key_i+1]['cover']=download_img(trim($v['cover']));
									$data[$key_i+1]['content']=get_content_by_url(trim($v['content_url']));
									list($data[$key_i+1]['sg_hit'],$data[$key_i+1]['sg_zan'])=get_comment_by_url(trim($v['content_url']));//赞和阅读
									//处理过的数据
									if($data[$key_i+1]['content']){//有内容才采集
										$tempData=array();
										$tempData=$data[$key_i+1];
										$info_id=0;
										list($info_id,$code,$msg)=caiji_to_qianjiapu_db($tempData);
										if($info_id){
											echoWrite($data[$key_i+1]['title']."==该条信息采集成功，msg:“".$msg."”，公众号：".$gzh_val['account']);	
										}else{
											echoWrite($data[$key_i+1]['title']."==该条信息采集失败，msg:“".$msg."”，公众号：".$gzh_val['account']);		
										}
									}else{
										echoWrite($data[$key_i]['title']."==该条信息采集失败，msg:“信息内容为空”，公众号：".$gzh_val['account']);		
									}
								}else{
									echoWrite($data[$key_i+1]['title']."==已经存在，或者标题内容为空，公众号：".$gzh_val['account']);	
								}
								$key_i++;//最后执行自增
							}	
						}else{
							$key_i++;//最后执行自增
						}// if end
					}//列表数据循环
				}else{
					echoWrite("该公众没有分类，请到后台手动归类，公众号：".$gzh_val['account'].";需重后台手动采集该公众号：".$gzh_val['account']);
					$caijiSucess=false;//采集不成功	
				}
			}else{
				echoWrite("该公众没有内容，或者公众号实时地址已经过期：".$url.";需重后台手动采集该公众号：".$gzh_val['account']);
				$caijiSucess=false;//采集不成功
			}
		}else{
			echoWrite("============公众号：“".$gzh_val['account']."”列表页返回来的错误信息：==================");
			echoWrite($content);
			echoWrite("=========获取不到改公众号的列表页内容，列表页为：".$url.";需重后台手动采集该公众号：".$gzh_val['account']."============");
			if(strpos($content,"请输入验证码")){//发送验证码给用户
				make_cookie_file_s();
				if(mktime(8,0,0,date("m"),date("j"),date("Y"))<time()&&time()<mktime(18,0,0,date("m"),date("j"),date("Y"))){//8-18点才发短信提示
					$lastUpdateTime_sendMsg=0;
					$sendMsg_file=dirname(__FILE__)."/sendMsg.txt";
					$lastUpdateTime_sendMsg=@filemtime($sendMsg_file);
					if((time()-$lastUpdateTime_sendMsg)>2*3600){
						Utility::DoPost("http://www.test.com/template_message.php",array('openid'=>'oFS7ntx23QYBGhMNPycgAvbfXPoE','title'=>'温馨提示，请在搜狗浏览器输入验证码！'));//小黑
						file_put_contents($sendMsg_file,"发送微信消息，已经发送小黑");	
					}
				}
			}
			$caijiSucess=false;//采集不成功
			/*if(strpos($content,"请输入验证码")!==false){//
				echoWrite("-----请输入验证码！----------");
				if(is_file($ing_file)){
					unlink($ing_file);//删除占进程文件...8：50-9：10执行
				}
				die("==yan zheng ma code===");
			}else{
					
			}*/
		}
		
		//标记该公众号已经采集，避免重复采集
		if($caijiSucess){//如果采集成功才更新采集时间，否则不更新采集时间
			Mysql::obj()->update("UPDATE `weixin_gzh` SET  `state` =  '1',`last_caiji_time` =  '".time()."' WHERE `id` =".$gzh_val['id']);
		}
		$temp_t=rand(1,6);
		echoWrite("============公众号：“".$gzh_val['account']."”-“".$gzh_val['nickname']."”已经采集完成，".$temp_t."s后进入下一个公众号采集==================");
		sleep($temp_t);//一分钟采集一个公众号，防止搜狗屏蔽
	}else{
		echoWrite("公众号：“".$gzh_val['account']."”今天已经采集，不重复采集！");	
	}
}//退出循环公众号
unlink($ing_file);//删除占进程文件...

function echoWrite($msg){
	$msg=$msg.";".date("Y-m-d H:i:s",time())."\r\n";//美观需要
	echo $msg;
	file_put_contents(dirname(__FILE__)."/log/".date("Y-m-d")."_log.txt",$msg,FILE_APPEND);
}

function get_content_by_url($url){
	$content="";
	$art_url='http://mp.weixin.qq.com'.$url;//获取了原文章地址
	$art_url=str_replace('&amp;',"&",$art_url);
	$matches=$listArr=array();
	$content=Utility::HttpRequest($art_url);
	preg_match_all('/id=\"js_content\"\>(.+)\<\/div\>/Uis',$content,$matches);
	if(!empty($matches)&&!empty($matches[1])&&isset($matches[1][0])){
		$content=$matches[1][0];
		$content=remoteImg($content);//下载图片地址后的内容
		if(strpos($content,'http://mmbiz.qpic.cn')!==false){//如果还有图片没远程，再远程一次
			$content=remoteImg($content,true);//下载图片地址后的内
		}
	}
	return $content;
}

function get_comment_by_url($url){
	$sg_hit=$sg_zan=0;
	$content="";
	$art_url='http://mp.weixin.qq.com'.$url;//
	$art_url=str_replace('&amp;',"&",$art_url);
	$comment_url=str_replace("/s?","/mp/getcomment?",$art_url);
	$comment_url=trim($comment_url,"=");
	$comment_url.="%3D&&uin=&key=&pass_ticket=&wxtoken=&devicetype=&clientversion=0&x5=0&f=json";
	$msg.=$comment_url;
	$content=Utility::HttpRequest($comment_url,null,$art_url);
	$msg.=$content;
	$contentArr=array();
	$contentArr=json_decode($content,true);
	$msg.=print_r($contentArr,true);
	$sg_hit = $contentArr['read_num'];
	$sg_zan = $contentArr['like_num'];
	return array($sg_hit,$sg_zan);
}

function remoteImg($content,$again=false){
	if($again){
		$reg = $reg ? $reg : '/<img(.*?) src="(.*?)"(.*?)>/i';
	}else{
		$reg = $reg ? $reg : '/<img(.*?) data-src="(.*?)"(.*?)\/>/i';
	}
	preg_match_all($reg,$content,$image);
	$havebdb = false;
	$remoteUrlTem = "";
	for($i=0,$j=count($image[2]);$i<$j;$i++){ 
		$havebdb=strpos($image[2][$i],"qianjiapu");
		if($havebdb!==false){
			$havebdb = false;
			continue;
		}
		$remoteUrlTem .= $image[2][$i]."||";
	}
	list($remoteurl,$content)= post_all_images(trim($remoteUrlTem,"||"),$content,$watermark);//远程图片
	return $content;
}
function data_exit($siteid,$title){
	$exit_title_arr=array();
	$exit_title_arr=Mysql::obj()->fetch_row("SELECT `id` FROM `infotab` WHERE `title`='".$title."' and siteid=".$siteid);//同一站点标题不能重复
	if(!empty($exit_title_arr)){
		return true;
	}else{
		return false;	
	}
}
function caiji_to_qianjiapu_db($data){
	if(!empty($data)&&$data['title']){//最少要存在标题，才插入
		if(strpos($data['cover'],'http://mmbiz.qpic.cn')!==false){//如果还有图片没远程，再远程一次，头图
			$data['cover']=download_img($data['cover']);
		}
		$infoarr = $cinfoarr = array();
		$infoarr['gzh_id'] = $data['gzh_id'];
		$infoarr['siteid'] = $data['siteid'];
		$infoarr['classid'] = $data['classid'];
		$infoarr['title'] = $data['title'];
		$infoarr['images'] = $data['cover']; 
		$infoarr['addtime'] = date('Y-m-d H:i:s',time());
		$infoarr['publictime'] = date('Y-m-d H:i:s',time());
		$infoarr['timestamp'] = $data['timestamp'];//搜狗时间
		$infoarr['checkadmin'] = $data['checked'];//是否为审核状态 
		$infoarr['visibledate'] = 1;
		$infoarr['userid'] = 1;//为采集用户
		$infoarr['hit'] = rand(527,1332);//一千为基数500-1000的随机数
		$infoarr['ip'] = Utility::GetRemoteIp();
		$infoarr['guide'] = $data['digest'];
		$infoarr['sg_hit'] = $data['sg_hit'];
		$infoarr['sg_zan'] = $data['sg_zan'];
		
		$cinfoarr['content']=str_replace("'","&sbquo;",$data['content']);//替换单引号,暂时不用addslashes此函数，如果出现bug可以用此函数解决
		$cinfoarr['content']=preg_replace('/[\x{10000}-\x{10FFFF}]/u', '',$cinfoarr['content']);//内容过滤下特殊字符
		
		$cinfoarr['content']=str_replace('src="/mp/','src="http://mp.weixin.qq.com/mp/',$cinfoarr['content']);
		$cinfoarr['content']=str_replace('src="/cgi-bin/','src="http://mp.weixin.qq.com/cgi-bin/',$cinfoarr['content']);
		
		$info_id = $info_id_c = 0;
		$sql="INSERT INTO `infotab` ( `gzh_id` , `siteid` , `classid` , `title` , `images` , `addtime` , `publictime` , `checkadmin` , `visibledate` , `userid` ,`hit`, `ip` , `guide`,`timestamp`,`sg_hit`,`sg_zan` ) VALUES ( ".$infoarr['gzh_id']." , ".$infoarr['siteid']." , ".$infoarr['classid']." , '".$infoarr['title']."' , '".$infoarr['images']."' , '".$infoarr['addtime']."' , '".$infoarr['publictime']."' , ".$infoarr['checkadmin']." , ".$infoarr['visibledate']." , ".$infoarr['userid'].", ".$infoarr['hit']." , '".$infoarr['ip']."' , '".$infoarr['guide']."', '".$infoarr['timestamp']."', '".$infoarr['sg_hit']."', '".$infoarr['sg_zan']."' )";
		if($info_id = Mysql::obj()->insert($sql)){//插入基本信息
			$sql_c="INSERT INTO `infotab_content` ( `id` , `content` ) VALUES ( ".$info_id." , '".$cinfoarr['content']."' )";
			//防止内容插入失败——start
			Mysql::obj()->insert($sql_c);//插入内容
			$msg="==OK==";
		}
	}else{
		$code=404;
		$msg="无数据";
	}
	return array($info_id,$code,$msg);
}
function download_img($remote_url){
	$remote_url=str_replace('tp=webp','',$remote_url);
	list($picurl,$content) = post_all_images($remote_url,$content,$watermark);
	return $picurl;
}


function post_all_images($url,$content,$watermark){
	$remoteurl = "";
	$domain="www.qianjiapu.com";
	$ftpimgdir="weixinbdb";
	$postdata=array('url2'=>$url,'domain'=>$domain,"filepath"=>$ftpimgdir,"act"=>"remote","watermark"=>$watermark);
	$picurl=Utility::DoPost("http://www.qianjiapu.com/photo_api/test.php",$postdata);
	$rg_arr=explode("||",$url);
	$re_arr=explode("||",trim($picurl,"||"));
	$content = str_replace($rg_arr,$re_arr,$content);
	$remoteurl = str_replace("||","|",trim($picurl,"||"));
	return array($remoteurl,str_replace("data-src","src",$content));
}

function make_cookie_file(){
	$cookie_file = dirname(__FILE__).'/cookie11.txt';
    $host = "http://ali-checkcode2.showapi.com";
    $path = "/checkcode";
    $method = "POST";
    $appcode = "**************";//为破解验证码秘钥
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    //根据API的要求，定义相对应的Content-Type
    array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
    $querys = "";
	//$img=str_replace("\r\n","",chunk_split(base64_encode(file_get_contents("http://mp.weixin.qq.com/mp/verifycode?cert=1487916511750.094"))));
	//获取验证码图片
	$yanma='http://weixin.sogou.com/antispider/util/seccode.php?tc=1487922283';
	$img=httpGet($yanma);
	//$img=httpGet("http://mp.weixin.qq.com/mp/verifycode?cert=1487916511750.094");
	$res=explode("\r\n\r\n",$img);
	$img=$res[1];
	$img=str_replace("\r\n","",chunk_split(base64_encode($img)));
    //echo "<img src='data:image/jpg;base64,".$img."'>";
    //提交API识别验证码
    $bodys = "convert_to_jpg=0&img_base64=".urlencode($img)."&typeId=3000";
    $url = $host . $path;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
    $res=curl_exec($curl);
	//var_dump($res);
	$res=explode("\r\n\r\n",$res);
	//print_r($res);
	$obj = json_decode($res[2],true);
	//print_r($obj["showapi_res_body"]);
	$vcode=$obj["showapi_res_body"]["Result"];  //返回验证码
	//提交验证，得到一个cookieok.txt
	//提交	http://mp.weixin.qq.com/mp/verifycode
	$url="http://weixin.sogou.com/antispider/thank.php";
	 $headers = array();
    //array_push($headers, "Referer:http://mp.weixin.qq.com/profile?src=3&timestamp=1487914769&ver=1&signature=qW*m2Oa0fM9S6neSJmCc8e9ofJFMRcIFkm5UycNFFrTg4KPC7SSvM4VhhO55u2lamE0X3hCu-5IbW0HIeUAE6A==");
    //根据API的要求，定义相对应的Content-Type
    array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
	$bodys="c=".$vcode."&r=%2fweixin%3Ftype%3d1%26query%3d苏州高新区发布%26ie%3dutf8%26_sug_%3dn%26_sug_type_%3d1%26w%3d01015002%26oq%3d%26ri%3d3%26sourceid%3dsugg%26sut%3d0%26sst0%3d1487922131107%26lkt%3d0%2C0%2C0%26p%3d40040108";
	//echo $bodys."<br>";
	 $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file); //使用上面获取的cookies
	// curl_setopt($curl, CURLOPT_COOKIEJAR,  dirname(__FILE__).'/cookieok.txt'); //存储cookies
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
    $results=curl_exec($curl);
	$res=explode("\r\n\r\n",$results);
	$obj = json_decode($res[1],true);
	$cookies="ABTEST=0|1487898282|v1; IPLOC=CN4403; SUID=5D9F913D2423910A0000000058AF86AA; SUIR=1487898282; SUV=0040178B3D919F5D58AF86AB6F20E523; SUID=5D9F913D3220910A0000000058AF873E; JSESSIONID=aaaFEYMCDkAa0_VruBFPv; accountTip=1; weixinIndexVisited=1; PHPSESSID=5elt95lhcvc7assdgnq7dec4m6; sct=3; SNUID=".$obj['id']."; successCount=1|Fri, 24 Feb 2017 08:22:27 GMT";
	file_put_contents(dirname(__FILE__).'/cookieok.txt',$cookies);
		
}
function make_cookie_file_s(){
	$cookie_file = dirname(__FILE__).'/cookie22.txt';
    $host = "http://ali-checkcode2.showapi.com";
    $path = "/checkcode";
    $method = "POST";
    $appcode = "**************";//为破解验证码秘钥
    $headers = array();
    array_push($headers, "Authorization:APPCODE " . $appcode);
    //根据API的要求，定义相对应的Content-Type
    array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
    $querys = "";
		
	$img=httpGets("http://mp.weixin.qq.com/mp/verifycode?cert=1487916511750.094");
	$res=explode("\r\n\r\n",$img);
	$img=$res[1];
	$img=str_replace("\r\n","",chunk_split(base64_encode($img)));
    $bodys = "convert_to_jpg=0&img_base64=".urlencode($img)."&typeId=2000";
    $url = $host . $path;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
    $res=curl_exec($curl);
	//var_dump($res);
	$res=explode("\r\n\r\n",$res);
	$obj = json_decode($res[2],true);
	$vcode=$obj["showapi_res_body"]["Result"];
	//提交	http://mp.weixin.qq.com/mp/verifycode
	$url="http://mp.weixin.qq.com/mp/verifycode";
	 $headers = array();
    array_push($headers, "Referer:http://mp.weixin.qq.com/profile?src=3&timestamp=1487914769&ver=1&signature=qW*m2Oa0fM9S6neSJmCc8e9ofJFMRcIFkm5UycNFFrTg4KPC7SSvM4VhhO55u2lamE0X3hCu-5IbW0HIeUAE6A==");
    //根据API的要求，定义相对应的Content-Type
    array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
	$bodys="input=".$vcode."&cert=".time();
	 $curl = curl_init();
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file); //使用上面获取的cookies
	  curl_setopt($curl, CURLOPT_COOKIEJAR,  dirname(__FILE__).'/cookieok_2.txt'); //存储cookies
    if (1 == strpos("$".$host, "https://"))
    {
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    }
    curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
	 $results=curl_exec($curl);
}
function httpGet($url) {
	$cookie_file = dirname(__FILE__).'/cookie11.txt';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, TRUE);    //表示需要response header
	curl_setopt($ch, CURLOPT_NOBODY, FALSE); //表示需要response body
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie_file); //存储cookies
	$result = curl_exec($ch);
	
	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
		return $result;
	}
	
	return NULL;
}
function httpGets($url) {
	$cookie_file = dirname(__FILE__).'/cookie22.txt';
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, TRUE);    //表示需要response header
	curl_setopt($ch, CURLOPT_NOBODY, FALSE); //表示需要response body
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);
	curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
	curl_setopt($ch, CURLOPT_TIMEOUT, 120);
	curl_setopt($ch, CURLOPT_COOKIEJAR,  $cookie_file); //存储cookies
	$result = curl_exec($ch);
	
	if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == '200') {
		return $result;
	}
	
	return NULL;
}

?>