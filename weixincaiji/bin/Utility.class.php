<?php
/**
 * @author: linhai6776@163.com
 */
class Utility 
{

    static public function GetRemoteIp($default='127.0.0.1')
    {
        $ip_string = $_SERVER['HTTP_CLIENT_IP'].','.$_SERVER['HTTP_X_FORWARDED_FOR'].','.$_SERVER['REMOTE_ADDR'];
        if ( preg_match ("/\d+\.\d+\.\d+\.\d+/", $ip_string, $matches) )
        {
            return $matches[0];
        }
        return $default;
    }


	static private function GetHttpContent($fsock=null) {
		$out = null;
		while($buff = @fgets($fsock, 2048)){
			$out .= $buff;
		}
		fclose($fsock);
		$pos = strpos($out, "\r\n\r\n");
		$head = substr($out, 0, $pos);    //http head
		$status = substr($head, 0, strpos($head, "\r\n"));    //http status line
		$body = substr($out, $pos + 4, strlen($out) - ($pos + 4));//page body
		if(preg_match("/^HTTP\/\d\.\d\s([\d]+)\s.*$/", $status, $matches)){
			if(intval($matches[1]) / 100 == 2){
				return $body;  
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	static public function DoGet($url,$ip=false){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = ($url2["port"] == "" ? 80 : $url2["port"]);
		if($ip){
			$host_ip = $ip;
		}else{
			$host_ip = @gethostbyname($url2["host"]);
		}
		$fsock_timeout = 2;  //2 second
		if(($fsock = fsockopen($host_ip, 80, $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"] .($url2["query"] ? "?".$url2["query"] : "");
		$in  = "GET " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		//$in .= "User-Agent: Payb-Agent\r\n";
		$in .= "User-Agent: Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/43.0.2327.5 Safari/537.36";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return self::GetHttpContent($fsock);
	}

	static public function DoPost($url,$post_data=array()){
		$url2 = parse_url($url);
		$url2["path"] = ($url2["path"] == "" ? "/" : $url2["path"]);
		$url2["port"] = ($url2["port"] == "" ? 80 : $url2["port"]);
		$host_ip = @gethostbyname($url2["host"]);
		$fsock_timeout = 2; //2 second
		if(($fsock = fsockopen($host_ip, 80, $errno, $errstr, $fsock_timeout)) < 0){
			return false;
		}
		$request =  $url2["path"].($url2["query"] ? "?" . $url2["query"] : "");
		$post_data2 = http_build_query($post_data);
		$in  = "POST " . $request . " HTTP/1.0\r\n";
		$in .= "Accept: */*\r\n";
		$in .= "Host: " . $url2["host"] . "\r\n";
		$in .= "User-Agent: Lowell-Agent\r\n";
		$in .= "Content-type: application/x-www-form-urlencoded\r\n";
		$in .= "Content-Length: " . strlen($post_data2) . "\r\n";
		$in .= "Connection: Close\r\n\r\n";
		$in .= $post_data2 . "\r\n\r\n";
		unset($post_data2);
		if(!@fwrite($fsock, $in, strlen($in))){
			fclose($fsock);
			return false;
		}
		return self::GetHttpContent($fsock);
	}

	static function HttpRequest($url, $data=array(),$re_url="") {
		$contents=$sg_url="";
		$key=0;
		//随机取的访问地址API
		$apiArr=array(
			'http://www.test.com/get_weixin_gzh_content.php',
			'http://www.test.com/get_weixin_gzh_content.php',
			'http://www.test.com/get_weixin_gzh_content.php',
			'http://www.test.com/get_weixin_gzh_content.php',
			'localhost',
		);
		$key=array_rand($apiArr);
		$data=array();
		$sg_url=$apiArr[$key]."?url=".urlencode($url)."&re_url=".urlencode($re_url);
		$key=4;//固定IP采集
		if($key==4){//localhost
			$contents=self::HttpRequests($url,$data,$re_url);//本地台服务访问
		}else{
			$contents=self::HttpRequests($sg_url);//此时是访问本地函数，访问我们自己的网址
		}
		return $contents;
	}
	static function HttpRequests($url, $data=array(),$re_url="") {// 用于外面访问和内部访问
		if ( !function_exists('curl_init') ) { return empty($data) ? self::DoGet($url) : self::DoPost($url, $data); }
		$ch = curl_init();
		if (is_array($data) && $data) {
			$formdata = http_build_query($data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $formdata);
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		if($re_url){
			curl_setopt($ch, CURLOPT_REFERER, $re_url);//设置来源
		}
		//设置cookie
		$cookie_jar=dirname(dirname(__FILE__))."/cookieok.txt";
		$lastUpdateTime_new=@filemtime($cookie_jar);//获取文件修改时间
		if(($lastUpdateTime_new+300)<time()){//每5分钟重新生成一次cookie
			//curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
		}
		//curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$r_1=rand(0,9);
		$r_2=rand(0,9);
		$r_3=rand(0,9);
		$uaArr=array(
			'Mozilla/5.0 (compatible; MSIE '.$r_1.'.0; Windows NT '.$r_2.'.1; WOW64; Trident/'.$r_3.'.0)',
			'Mozilla/5.0+(Windows+NT+6.1)+AppleWebKit/'.$r_2.'37.1+(KHTML,+like+Gecko)+Chrome/21.0.1'.$r_1.'80.89+Safari/5'.$r_3.'7.1;+360Spider(compatible;+HaosouSpider;+http://www.haosou.com/help/help_3_2.html)',
			'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/'.$r_3.'37.36 (KHTML, like Gecko) Chrome/50.0.2'.$r_2.'61.102 Safari/5'.$r_1.'7.36',
			'User-Agent: Payb-Agent',
			'User-Agent: Payb-Agent MicroMessenger'
		);
		$uaKey=array_rand($uaArr);
		$headers=array(
    'User-Agent: '.$uaArr[$uaKey],
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Encoding: identity',
    'Accept-Language: zh-CN,zh;q=0.8',
   'Cookie: '.file_get_contents($cookie_jar)
);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


		//curl_setopt($ch, CURLOPT_USERAGENT,$uaArr[$uaKey]);//随机抽取UA头
		$result = curl_exec($ch);
		return $result ? $result : ( empty($data) ? self:: DoGet($url) : self::DoPost($url, $data) );
	}
}
