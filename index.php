<?php
header("Content-Type: text/html;charset=utf-8"); 
ini_set("memory_limit", "1024M");
ini_set('max_execution_time', '360000');
ini_set( 'display_errors', 1 );
error_reporting(E_ERROR | E_WARNING | E_PARSE);
//error_reporting(E_ALL);


	$path = "./inc";
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

act = グラフ表示

--------------------------------*/

if($act == "chart"){
	
	$inst = DBConnection::getConnection($DB_URI);
	//30日前からの集計
	$sin = array();
	$sins = array();
	$totals = array();
	$dates = array();
	for($i=10;$i>=0;$i--){
		$d = date("Y-m-d",strtotime("-".$i." day"));
		$d2 = date("j",strtotime("-".$i." day"));
		$dates[] = $d;
		foreach($shop_list as $key2 => $val2){
			//if($key2 == 0){
				$sql = "select sum(`total`) as `sum` from `item` where `date` LIKE '".$d."%' and `shop_id`='".$key2."' group by `shop_id` order by `shop_id`";
				$ret = $inst->search_sql($sql);
				if($ret["count"] > 0){
					$totals[$key2][] = (int)$ret["data"][0]["sum"];
					$sins[$key2][] = array("val"=>"[".$d2.",".$ret["data"][0]["sum"]."]");
				}else{
					$totals[$key2][] = 0;
					$sins[$key2][] = array("val"=>"[".$d2.",0]");
				}
			//}
			$data["names".$key2] = $val2;
		}
	}
	$data["max"] = max($totals);
	$data["min"] = min($totals);

	//var_dump(max($totals[0]));
	$colors = array("#FA5833", "#2FABE9","#006e54","#006e54","#824880","#393e4f","#eb6ea5");
	
	$cnts = array();
	for($i=0;$i<=6;$i++){
		//var_dump($sins[$i]);
		if(strlen(max($totals[$i])) > 0){$max = max($totals[$i]);}else{$max = 1000;}
		if(strlen(min($totals[$i])) > 0){$min = min($totals[$i]);}else{$min = 0;}
		if($max == $min){$min = 0;}
		$cnts[] = array("key"=>$i,"name"=>$shop_list[$i],"js_arrays"=>"var sin".$i."=[];","sin"=>$sins[$i],"lavels"=>'{ data: sin'.$i.', label: "'.$shop_list[$i].'"}',"colors"=>$colors[$i],"max"=>$max,"min"=>$min);
	}
	$data["cnts"] = $cnts;
	
	$html->t_include("chart.html", $data);
	exit;
}
function day_diff($date1, $date2) {
 
    // 日付をUNIXタイムスタンプに変換
    $timestamp1 = strtotime($date1);
    $timestamp2 = strtotime($date2);
 
    // 何秒離れているかを計算
    $seconddiff = abs($timestamp2 - $timestamp1);
 
    // 日数に変換
    $daydiff = $seconddiff / (60 * 60 * 24);
 
    // 戻り値
    return $daydiff;
 
}



/*----------------------------

act = 登録データ2

--------------------------------*/

if($act == "data2"){
	$data["title"] = "取得データ一覧";
	
	$inst = DBConnection::getConnection($DB_URI);
	
	$sql = "TRUNCATE TABLE `tmp`;";
	$inst->db_exec($sql);
	
	$d1 = (isset($_REQUEST['d1'])) ? $_REQUEST['d1'] : date("Y-m-d",strtotime("-10 day"));
	$d2 = (isset($_REQUEST['d2'])) ? $_REQUEST['d2'] : date("Y-m-d");
	$data["d1"] = $d1;
	$data["d2"] = $d2;
	
	$sa = day_diff($d1, $d2);
	
	$list = array();
	for($i=0;$i<=$sa;$i++){
		$d2 = date("m月d日",strtotime($d1." +".$i." day"));
		$d = date("Y-m-d",strtotime($d1." +".$i." day"));
		$d9 = date("Ymd",strtotime($d1." +".$i." day"));
		$sql1 = array();$sql0 = array();$total = 0;
		foreach($shop_list as $key2 => $val2){
			$ww = "";
			
			//if($key2 == 0){
				$sql0[] = "`d".$key2."`";
			if($key2 == 0){
				$ww = " and `status`='処理済'";
				$sql = "select sum(`total`) as `sum` from `item` where `no` LIKE '%".$d9."%' and `shop_id`='".$key2."'".$ww." group by `shop_id` order by `shop_id`";
			}else{
				$sql = "select sum(`total`) as `sum` from `item` where `date` LIKE '".$d."%' and `shop_id`='".$key2."'".$ww." group by `shop_id` order by `shop_id`";
			}
				$ret = $inst->search_sql($sql);
				if($ret["count"] > 0){
					$sql1[] = $ret["data"][0]["sum"];
				}else{
					$sql1[] = 0;
				}
				$total += $ret["data"][0]["sum"];
			//}
		}
		$sql = "insert into `tmp`(`hi`,`total`,".implode(",",$sql0).") values('".$d."','".$total."','".implode("','",$sql1)."')";
		$inst->db_exec($sql);
	}
	$list = array();
	$sql = "select * from `tmp` order by `hi` desc";
	$ret = $inst->search_sql($sql);
	$alls = array();
	if($ret["count"] > 0){
		foreach($ret["data"] as $key => $val){
			$total = 0;
			foreach($shop_list as $key2 => $val2){
				$alls["d".$key2] += $val["d".$key2];
				$val["d".$key2] = number_format($val["d".$key2]);
			}
			//前日差
			$zen = array();
			$sql5 = "select * from `tmp` where `hi` < '".$val["hi"]."' limit 1";
			$ret5 = $inst->search_sql($sql5);
			if($ret5["count"] > 0){
				$zen = $ret5["data"][0];
				$val["sa"] = number_format($val["total"]-$zen["total"]);
			}
			$val["total"] = number_format($val["total"]);
			$list[] = $val;
		}
	}
			$allstotal = 0;
			foreach($alls as $key => $val){
				$allstotal += $val;
				$alls[$key] = number_format($val);
			}
			$alls["hi"] = "合計";
			$alls["total"] = number_format($allstotal);
	$list[] = $alls;
	
	
	$data["list"] = $list;
//var_dump($alls);


	$titles = array();
	foreach($shop_list as $key => $val){
		$titles[] = array("val"=>$val);
	}
	$data["titles"] = $titles;
	
	$html->t_include("data2.html", $data);
	exit;
}




/*----------------------------

act = 登録データ

--------------------------------*/

if($act == "data"){
	$data["title"] = "取得データ一覧";
	
	$max_list = array("20","50","100","300","500");
	
	$d1 = (isset($_REQUEST['d1'])) ? $_REQUEST['d1'] : "";
	$d2 = (isset($_REQUEST['d2'])) ? $_REQUEST['d2'] : "";
	$h1 = (!empty($_REQUEST['h1'])) ? $_REQUEST['h1'] : "00";
	$h2 = (!empty($_REQUEST['h2'])) ? $_REQUEST['h2'] : "00";
	$shop = (isset($_REQUEST['shop'])) ? $_REQUEST['shop'] : array("0","1","2","3","4","5","6");
	$keyword = (isset($_REQUEST['keyword'])) ? $_REQUEST['keyword'] : "";
	$order = (isset($_REQUEST['order'])) ? $_REQUEST['order'] : "date";
	$desc = (isset($_REQUEST['desc'])) ? $_REQUEST['desc'] : "desc";
	$maxpage = (isset($_REQUEST['maxpage'])) ? $_REQUEST['maxpage'] : 20;
	
	
	$data["order"] = $order;
	$data["desc"] = $desc;
	$data["d1"] = $d1;
	$data["d2"] = $d2;
	$data["keyword"] = $keyword;

	$inst = DBConnection::getConnection($DB_URI);
	
	if($_REQUEST["mode"] == "del"){
		$id = $_REQUEST["id"];
		if($id > 0){
			$sql = "delete from `item` where `id` = '".$id."'";
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
	if(strlen($keyword) > 0){
		$where .= " and `title` LIKE '%".$keyword."%'";
	}
	if(strlen($d1) > 0){
		$where .= " and `date` >= '".$d1." ".$h1.":00:00'";
	}
	if(strlen($d2) > 0){
		$where .= " and `date` <= '".$d2." ".$h2.":00:00'";
	}
	if(count($shop) > 0){
		$where .= " and `shop_id` IN (".implode(",",$shop).")";
	}
	if($_SESSION["USER_LOGIN_SHOPS"]["id"] == "2"){
		$where .= " and (`title` LIKE '%LIMIA%' or `title` LIKE '%MIZUTAMA%' or `title` LIKE '%WAGON WORKS%')";
	}elseif($_SESSION["USER_LOGIN_SHOPS"]["id"] == "3"){
		$where .= " and (`title` LIKE '%HARUHARU%' or(`shop_id`=1 and `title` LIKE '%CASSETTE%') or(`shop_id`=1 and `title` LIKE '%ICHIGO%') or(`shop_id`=1 and `title` LIKE '%HEART%') or(`shop_id`=1 and `title` LIKE '%JUICE%'))";
	}elseif($_SESSION["USER_LOGIN_SHOPS"]["id"] == "4"){
		$where .= " and `title` LIKE '%Colleen%'";
	}elseif($_SESSION["USER_LOGIN_SHOPS"]["id"] == "5"){
		$where .= " and `title` LIKE '%JJ%'";
	}elseif($_SESSION["USER_LOGIN_SHOPS"]["id"] == "6"){
		$where .= " and `title` LIKE '%超かわいい部%' or `title` LIKE '%鶴嶋%' or `title` LIKE '%浪花%' or `title` LIKE '%東海林%' or `title` LIKE '%平松%' or `title` LIKE '%姫香%'";
	
	}
	
	if($_REQUEST["csv"] == "csv"){
		$csv = array();
		$sql = "select * from `item` ".$where." order by `".$order."` ".$desc."";
		//echo $sql;
		$ret = $inst->search_sql($sql);
		if($ret["count"] > 0){
			foreach($ret["data"] as $key => $val){
				$val["shop_id"] = $shop_list[$val["shop_id"]];
				$val["amount"] = $val["amount"];
				$val["total"] = $val["total"];
				$csv[] = $val;
			}
		}
		$top = array("id","shop","注文番号","注文日","paymentdate","商品名","商品価格","送料","合計金額","状態","データ登録日");
		download_csv($csv, date("YmdHis").".csv",$top);
	}
	
	$list = array();
	$sql = "select * from `item` ".$where." order by `".$order."` ".$desc."";
	//echo $sql;
	$ret = $inst->search_sql($sql.$limit);
	if($ret["count"] > 0){
		foreach($ret["data"] as $key => $val){
			$val["shop_id"] = $shop_list[$val["shop_id"]];
			$val["amount"] = number_format($val["amount"]);
			$val["total"] = number_format($val["total"]);
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

	$data["pagingstring"] = Paging ((int)$page,"data",(int)$page_count,$para);
	
	//時間リスト
	$h1_list = array();
	$h2_list = array();
	for($i=0;$i<=23;$i++){
		if(strlen($i) == 1){$ii = "0".$i;}else{$ii=$i;}
		if($ii == $h1){$sel="selected";}else{$sel="";}
		if($ii == $h2){$sel2="selected";}else{$sel="";}
		$h1_list[] = array("key"=>$ii,"sel"=>$sel);
		$h2_list[] = array("key"=>$ii,"sel"=>$sel2);
	}
	$data["h1_list"] = $h1_list;
	$data["h2_list"] = $h2_list;
	
	//ショップ指定
	$shops = array();
	foreach($shop_list as $key => $val){
		if(in_array($key,$shop)){$sel="checked";}else{$sel="";}
		$shops[] = array("key"=>$key,"value"=>$val,"sel"=>$sel);
	}
	$data["shops"] = $shops;
	
	
	//ページ表示数
	$mlist = array();
	foreach($max_list as $key => $val){
		if($val == $maxpage){$sel="selected";}else{$sel="";}
		$mlist[] = array("key"=>$val,"sel"=>$sel);
	}
	$data["mlist"] = $mlist;
	
	//店舗後との総トータル
/*
	$where = " where 1";
	if(strlen($d1) > 0){
		$where .= " and `date` >= '".$d1." ".$h1.":00:00'";
	}
	if(strlen($d2) > 0){
		$where .= " and `date` < '".$d2." ".$h2.":00:00'";
	}
	if($_SESSION["USER_LOGIN_SHOPS"]["id"] == "2"){
		$where .= " and (`title` LIKE '%LIMIA%' or `title` LIKE '%MIZUTAMA%' or `title` LIKE '%WAGON WORKS%')";
	}elseif($_SESSION["USER_LOGIN_SHOPS"]["id"] == "3"){
		$where .= " and (`title` LIKE '%HARUHARU%' or(`shop_id`=1 and `title` LIKE '%CASSETTE%') or(`shop_id`=1 and `title` LIKE '%ICHIGO%') or(`shop_id`=1 and `title` LIKE '%HEART%') or(`shop_id`=1 and `title` LIKE '%JUICE%'))";
	}
*/
	$items = array();
	for($i=0;$i<=6;$i++){
		//$where1 = "";
		//$where1 = $where." and `shop_id`='".$i."'";
		$total = 0;
		//$sql = "select `amount` from `item` ".$where." and `shop_id`='".$i."' group by `no`";
		$sql = "select `amount` from `item` ".$where." and `shop_id`='".$i."'";
		$ret = $inst->search_sql($sql);
		if($ret["count"] > 0){
			foreach($ret["data"] as $key => $val){
				$total += $val["amount"];
			}
		}
		$items[] = array("shop"=>$shop_list[$i],"fee"=>number_format($total),"cnt"=>number_format($ret["count"]));
	}
	$data["items"] = $items;

	$html->t_include("data.html", $data);
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