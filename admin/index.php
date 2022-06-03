<?php
header("Content-Type: text/html;charset=utf-8"); 
ini_set("memory_limit", "1024M");
ini_set('max_execution_time', '360000');
ini_set( 'display_errors', 1 );
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//error_reporting(E_ALL);


	$path = "../inc";
	require_once($path."/conf.php");
	require_once($path."/my_db.inc");
	require_once($path."/htmltemplate.inc");
	require_once($path."/errlog.inc");
	$DB_URI = array("host" => $DB_SV, "db" => $DB_NAME, "user" => $DB_USER, "pass" => $DB_PASS);

	session_start();

	$data = array();
	$data["admin"]["title"] = SITE_INFO;
	$data["admin"]["url"] = BASE_URL;

	$max_page = 20;

	define("SCRIPT_ENCODING", "UTF-8");
	// データベースの漢字コード
	define("DB_ENCODING", "UTF-8");
	// メールの漢字コード(UTF-8かJIS)
	define("MAIL_ENCODING", "JIS");

$act = (isset($_REQUEST['act'])) ? $_REQUEST['act'] : "";



//ドキュメントルートURl設定
$url = "http://".$_SERVER["HTTP_HOST"]."/";


// --------------------------------
// 各ページの処理

$html = &htmltemplate::getInstance();

/*--------------------------------*/
if($act == "logout"){
	$_SESSION = array();
	session_destroy();
	$act = "login";
}

/*----------------------------

セッションが切れていたらログインページへ

--------------------------------*/

if(!isset($_SESSION["USER_LOGIN_SHOPS"])){
	$act = "login";
}else{
	$data["name"] = $_SESSION["USER_LOGIN_SHOPS"];
}



/*----------------------------

act = login　ログイン

--------------------------------*/

if($act == "login"){
	if ($_REQUEST["id"] && $_REQUEST["passwd"]) {
		$id = htmlspecialchars($_REQUEST["id"]);
		$passwd = htmlspecialchars($_REQUEST["passwd"]);
		
		$inst = DBConnection::getConnection($DB_URI);
		//ログイン情報取得
		$sql = "select * from `admin` where `login_id`='".$_REQUEST["id"]."' and `login_pw`='".$_REQUEST["passwd"]."'";

		$ret = $inst->search_sql($sql);
		if($ret["count"] > 0){
			
				$_SESSION["USER_LOGIN_SHOPS"] = $ret["data"][0];
				$login_id = $ret["data"][0]["login_id"];
				$login_pw = $ret["data"][0]["login_pw"];

				$act="top";
			
		}else{
			$data["message"] = "ログインできません。IDとパスワードを確認してください。";
		}
	}
	if($act == "login"){
		$html->t_include("login.html", $data);
		exit;
	}
}

if($act == "csvs"){///csvでBASE登録
	$inst = DBConnection::getConnection($DB_URI);
	setlocale(LC_ALL, 'ja_JP.UTF-8');
 
	$file = 'waylly.csv';
	$data1 = file_get_contents($file);
	$data1 = mb_convert_encoding($data1, 'UTF-8', 'sjis-win');
	$temp = tmpfile();
	$csv  = array();
	 
	fwrite($temp, $data1);
	rewind($temp);
	 $cnt=0;
	while (($data1 = fgetcsv($temp, 0, ",")) !== FALSE) {
	    //$csv[] = $data1;
	    if($cnt > 0){
	    	$keys = $data1[0];
	    	$sql = "select * from `item` where `shop_id`=2 and `no`='".$data1[0]."'";
	    	$ret = $inst->search_sql($sql);
	    	if($ret["count"] == 0){
	    		$sql = "INSERT INTO `item` (`shop_id`, `no`, `date`, `paymentdate`, `title`, `amount`, `soryo`, `total`, `status`, `regist`) VALUES";
	    		$sql .= "('2', '".$data1[0]."', '".$data1[1]."', '', '".$data1[18]."', '".$data1[20]."', '".$data1[23]."', '".$data1[22]."', '".$data1[26]."', now())";
	    		$inst->db_exec($sql);
	    		echo $data1[0]."<br />\n";
	    	}
	    }
	    $cnt++;
	}
	fclose($temp);
	 
	var_dump($csv);
	
	
	
	exit;
}



/*----------------------------

act =  ログイン情報変更

--------------------------------*/
if($act == "user_setup"){
	
	$data["title"] = "ログイン情報更新";
	$inst = DBConnection::getConnection($DB_URI);
	if($_REQUEST["mode"]=="update"){
		$login_id = $_REQUEST["login_id"];
		$login_pw = $_REQUEST["login_pw"];
		$sql = "update `admin` set `login_id`='".$login_id."',`login_pw`='".$login_pw."' where id=".$_SESSION["USER_LOGIN_SHOPS"]["id"];
		$ret = $inst->db_exec($sql);
		$data["message"] = "変更しました。";
	}
	$sql = "select * from `admin` where id=".$_SESSION["USER_LOGIN_SHOPS"]["id"];
	$ret = $inst->search_sql($sql);
	if($ret["count"] > 0){
		$data["login_id"] = $ret["data"][0]["login_id"];
		$data["login_pw"] = $ret["data"][0]["login_pw"];
	}
	$html->t_include("user_setup.html", $data);
	exit;
}


/*----------------------------

act =  ユーザー登録

--------------------------------*/
if($act == "users_edit"){
	
	$data["title"] = "ユーザー登録";
	$inst = DBConnection::getConnection($DB_URI);
	if($_REQUEST["mode"]=="edit"){
		$login_id = $_REQUEST["login_id"];
		$login_pw = $_REQUEST["login_pw"];
		$sql = "update `admin` set `login_id`='".$login_id."',`login_pw`='".$login_pw."' where id=".$_SESSION["USER_LOGIN_SHOPS"]["id"];
		$ret = $inst->db_exec($sql);
		$data["message"] = "登録しました。";
	}



	$html->t_include("users_edit.html", $data);
	exit;
}




/*----------------------------

act = ユーザー管理

--------------------------------*/

if($act == "users"){
	$data["title"] = "ユーザー管理";

	$order = (isset($_REQUEST['order'])) ? $_REQUEST['order'] : "id";
	$desc = (isset($_REQUEST['desc'])) ? $_REQUEST['desc'] : "desc";
	
	$inst = DBConnection::getConnection($DB_URI);
	
	if($_REQUEST["mode"] == "del"){
		$id = $_REQUEST["id"];
		if($id > 0){
			$sql = "delete from `users` where `id` = '".$id."'";
			$inst->db_exec($sql);
			$data["message"] = "削除しました。";
		}
	}
	
	
	
	$page = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
	$data["page"] = $page;
	$start = ($page-1)*$maxpage;
	if($start<0){$start=0;}
	$limit = " limit ".$start.",".$maxpage;
	
	
	$para = "";
	$where = " where 1";
	
	$list = array();
	$sql = "select * from `users` ".$where." order by `".$order."` ".$desc."";
	//echo $sql;
	$ret = $inst->search_sql($sql.$limit);
	if($ret["count"] > 0){
		foreach($ret["data"] as $key => $val){
			$list[] = $val;
		}
	}
	$data["list"] = $list;
	
	$data_count = 0;
	$ret2 = $inst->search_sql($sql);
	//全データ件数取得
	$data_count = $ret2["count"];
	$data["cnt"] = number_format($data_count);

	$page_count = ceil($data_count / $maxpage);

	$data["pagingstring"] = Paging ((int)$page,"users",(int)$page_count,$para);

	$html->t_include("users.html", $data);
	exit;
}







$html->t_include("top.html", $data);
exit;

function Paging ($page,$act,$page_count,$para=""){

	$pagingstring = "";
	if ($page > 1) {
		$pagingstring .= "<li><a href=\"javascript:void(0);\" class=\"pageBtn\" rel=\"".strval($page - 1)."\">Prev</a></li>";
		for ($i = 5; $i >= 1; $i--) {
			if ($page - $i >= 1) {
				$pagingstring .= "<li><a href=\"javascript:void(0);\" class=\"pageBtn\" rel=\"".strval($page - $i)."\">".strval($page - $i)."</a></li>";
			}
		}
	}
	$pagingstring .= "<li class=\"active\">".strval($page)."</li>";
	if ($page < $page_count) {
		for ($i = 1; $i <= 5; $i++) {
			if ($page + $i <= $page_count) {
				$pagingstring .= "<li><a href=\"javascript:void(0);\" class=\"pageBtn\" rel=\"".strval($page + $i)."\">".strval($page + $i)."</a></li>";
			}
		}
		$pagingstring .= "<li><a href=\"javascript:void(0);\" class=\"pageBtn\" rel=\"".strval($page + 1)."\">Next</a></li>";
	}
	return $pagingstring;
}

function download_csv($data, $filename,$top){
	header("Content-disposition: attachment; filename=" . $filename);
	header("Content-type: text/x-csv; charset=Shift_JIS");
	echo $fp,mb_convert_encoding(implode(",", $top), "Shift_Jis", "utf-8") . "\r\n";
	foreach ($data as $val) {
		$csv = array();
		foreach ($val as $item) {
			array_push($csv, $item);
		}
		echo '"'.mb_convert_encoding(implode('","', $csv), "Shift_Jis", "utf-8").'"'."\r\n";
		//echo mb_convert_encoding(implode(",", $csv), "Shift_Jis", "utf-8") . "\r\n";
	}
	exit;
}




?>