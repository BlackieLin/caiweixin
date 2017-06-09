<?php
/**
 * @author: linhai6776@163.com
 */
class Mysql{
	
	private static $obj = null;
	private static $objli = null;
	
	public static function obj(){
		if(class_exists("Mysqli")&&!self::$obj){
			self::$obj = new Mysql();
		}
		return self::$obj;
	}
	
	private function __construct(){
		
		$local = "127.0.0.1";
		$username = "root";
		$userpass = "123";
		$dbname = "test";
		
		
		
		$obj = new mysqli($local,$username,$userpass,$dbname); 
		if(mysqli_connect_errno()){
			echo "数据库连接错误".mysqli_connect_error();
			$obj=null;
		}
		$obj->query("SET NAMES UTF8");
		self::$objli = $obj;
	}
	
	public function fetch_row($sql,$one=true){
		
		$data = array();
		$fetch_obj = self::$objli->query($sql);
		if(is_object($fetch_obj)){
			while($row=$fetch_obj->fetch_assoc()) {
				if($one){
					$data = $row;
					break;//直接跳出循环
				}else{
					$data[] = $row;
				}
			}
		}
		return $data;
	}
	
	public function insert($sql){
		$rsObj=self::$objli->query($sql);
		if($rsObj!=1){
			echo $sql."|".self::$objli->errno."|".self::$objli->error;
		}
		return self::$objli->insert_id;
	}
	
	public function update($sql){
		$rsObj=self::$objli->query($sql);
		if($rsObj!=1){
			echo $sql."|".self::$objli->errno."|".self::$objli->error;
		}
	}
	
	public function delete($sql){
		$rsObj=self::$objli->query($sql);
		if($rsObj!=1){
			echo $sql."|".self::$objli->errno."|".self::$objli->error;
		}
	}
	
}
