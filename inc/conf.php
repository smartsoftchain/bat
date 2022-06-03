<?php
$DB_SV="localhost";
$DB_NAME="bat";
$DB_USER="root";
$DB_PASS="06110204";



function Encode_str($val){
	$str = mb_convert_encoding($val, "UTF-8", "shift-jis");
	return $str;
}
function Decode_str($val){
	$str = mb_convert_encoding($val, "shift-jis", "UTF-8");
	return $str;
}
function Encode_str2($val){
	$str = mb_convert_encoding($val, "euc-jp", "UTF-8");
	return $str;
}

function Encode_array($val){
	return mb_convert_variables("UTF-8", "shift-jis",$val);
}

function Encode_array2($val){
	$new_val = array();
	foreach($val as $key => $val2){
		$new_val[$key] = Encode_str($val2);
		//$new_val[$key] = str_replace($a,$b,$new_val[$key]);
	}
	return $new_val;
}


if (!function_exists('json_encode')) {
	require 'JSON.php';
	function json_encode($value) {
		$s = new Services_JSON();
		return $s->encodeUnsafe($value);
	}
	function json_decode($json, $assoc = false) {
		$s = new Services_JSON($assoc ? SERVICES_JSON_LOOSE_TYPE : 0);
		return $s->decode($json);
	}
}


?>