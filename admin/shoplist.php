<?php
header("Content-Type: text/html;charset=utf-8"); 
ini_set("memory_limit", "1024M");
ini_set('max_execution_time', '360000');
ini_set( 'display_errors', 0 );
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//error_reporting(E_ALL);


	$path = dirname(__FILE__)."/../inc";
	require_once($path."/conf.php");
	require_once($path."/my_db.inc");
	require_once($path."/htmltemplate.inc");
	require_once($path."/errlog.inc");
	require_once($path."/mail.php");
	$DB_URI = array("host" => $DB_SV, "db" => $DB_NAME, "user" => $DB_USER, "pass" => $DB_PASS);
	$inst = DBConnection::getConnection($DB_URI);
	
	define("SCRIPT_ENCODING", "UTF-8");
	// データベースの漢字コード
	define("DB_ENCODING", "UTF-8");
	// メールの漢字コード(UTF-8かJIS)
	define("MAIL_ENCODING", "JIS");
	
	
	//SHOPLIST
	//https://service.shop-list.com/shopadmin/login/Alert
	
	//取得日付
	//$startday = date("Y-m-d");
	$startday = "2018-04-01";
	
	$cookie_file_path = dirname(__FILE__).'/log/cookie1.txt';
	@unlink($cookie_file_path);
	touch($cookie_file_path);
	
	//ログインページ
	$urls= "https://service.shop-list.com/shopadmin/";
	$urls1= "https://service.shop-list.com/shopadmin/login/Login";
	//検索ページ
	$urls2 = "https://service.shop-list.com/shopadmin/summary/DailySalesProductDetail/?startDate=".$startday."&endDate=".$startday."&csv_flg=1";
	//
	
		//クッキー保存ファイルを作成
	   $params = array( 
		    "directory" => 'waylly', 
		    "login_id" => '5fc2fb4a', 
		    "login_pass"  => "case111222",
		    "Submit" => 'login'
		); 
	
	
	$headers  		=  array( "Content-type: text/html");
	$agent 			= "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36";
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $urls);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file_path);
	$put = curl_exec($ch) or dir('error ' . curl_error($ch)); 
	curl_close($ch);
	
	

	$ch2 = curl_init();
	curl_setopt($ch2, CURLOPT_URL, $urls1);
	curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch2, CURLOPT_HEADER, TRUE);
	//curl_setopt($ch2, CURLINFO_HEADER_OUT, TRUE);
	//curl_setopt($ch2, CURLOPT_USERAGENT, $agent);
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, TRUE);
	//curl_setopt($ch2, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch2, CURLOPT_POST, TRUE);
	curl_setopt($ch2, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookie_file_path);
	$output0 = curl_exec($ch2) or dir('error ' . curl_error($ch2)); 

	curl_close($ch2);


	$ch3 = curl_init();
	curl_setopt($ch3, CURLOPT_URL, $urls2);
	curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch3, CURLOPT_HEADER, TRUE);
	curl_setopt($ch3, CURLOPT_USERAGENT, $agent);
	//curl_setopt($ch2, CURLINFO_HEADER_OUT, false);
	curl_setopt($ch3, CURLOPT_RETURNTRANSFER, TRUE);
	//curl_setopt($ch3, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch3, CURLOPT_COOKIEFILE, $cookie_file_path);
	curl_setopt($ch3, CURLOPT_COOKIEJAR, $cookie_file_path);
	curl_setopt($ch3, CURLOPT_POST, TRUE);
	$output = curl_exec($ch3) or dir('error ' . curl_error($ch3));
//$info = curl_getinfo ($ch3);
//var_dump ($info);
	curl_close($ch3);

	//echo $urls2;
	//print_r($output);
	//exit;
	//第２段階
	file_put_contents(dirname(__FILE__)."/csv/".date("Ymd")."_shoplist.csv", $output);
	
	if(file_exists(dirname(__FILE__)."/csv/".date("Ymd")."_shoplist.csv")){
	
		
		$filename = date("Ymd")."_shoplist.csv";
		$fpath = "./csv/".$filename;
		if(file_exists($fpath)){
			echo "csv-start\n";
			$fp = fopen($fpath, "r");
			$count = 0;
			$new_array = array();
			while ($array = fgetcsv( $fp )) {
				if($count > 0){
					$item = array();
					$item = Encode_array2($array);
					print_r($item);
				}
				$count++;
			}
		}
	
	}
	
	

echo "end\n";

exit;

?>