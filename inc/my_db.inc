<?php

include_once("errlog.inc");

class DBConnection {
    var $_conn = null;	// コネクション
    /**
	 * @name DBConnection
     * @desc コンストラクタ
     **/
    function DBConnection() {
		//
    }
    /**
     * @name logoutput
	 * @desc ログ出力処理
     */
	function logoutput($level, $message, $file=null, $line=null)
	{
		if (DEBUG) {
			//$log = ErrLog::getInstance(null, 9);
		} else {
			//$log = ErrLog::getInstance(null, 3);
		}
		//$log->ErrLog_Write($level, $message, $file, $line);
	}
    /**
     * @name getConnection
	 * @desc データベースアクセス用インスタンス作成
     */
    function &getConnection($DB_URI) {
        static $instance = null;
        if ($instance == null) {
            $instance = new DBConnection();
            // DB 接続
            $ret = $instance->db_open($DB_URI);
			if (!$ret) {
				$instance = null;
			}
        }
        return $instance;
    }
	/**
	 * @name db_open
	 * @desc データベースオープン
	 * @param $uri データベース定義（ホスト、DB名、アカウントなど）
	 * @return 実行結果
	 **/
	function db_open($uri)
	{
		$this->_conn = mysql_connect($uri["host"], $uri["user"], $uri["pass"]);
		if (!$this->_conn) {
			$this->logoutput(ERRLOG_ERROR, "mysql_connect error.", __FILE__, __LINE__);
			return false;
		}
		mysql_select_db($uri["db"]);
		$sql = "SET NAMES utf8";
		$res = mysql_query($sql, $this->_conn);

		return true;
	}
	/**
	 * @name db_exec
	 * @desc SQL実行
	 * @param $sql 実行するSQL文
	 * @return 実行結果
	 **/
	function db_exec($sql, $log=true)
	{
		//if (DEBUG && $log) {
			//$this->logoutput(LOG_DEBUG, $sql);// デバッグ用にSQLをログへ
		//}
		$res = mysql_query($sql, $this->_conn);
		if (!$res) {
			$this->logoutput(LOG_ERROR, mysql_error($this->_conn), __FILE__, __LINE__);
		}
		return $res;
	}
	// トランザクション開始
	function db_begin_transaction()
	{
		return $this->db_exec("BEGIN");
	}

	// トランザクション異常終了
	function db_rollback()
	{
		return $this->db_exec("ROLLBACK");
	}

	// トランザクション終了
	function db_commit()
	{
		return $this->db_exec("COMMIT");
	}
	/**
	 * @name escape_string
	 * @desc データベースの文字コード変換とエスケープ
	 * @return 変換後文字列
	 **/
	function escape_string($str, $raw=false)
	{
		if ($raw) {
			return mysql_escape_string($str);
		}
		if (DB_ENCODING == SCRIPT_ENCODING) {
			return mysql_escape_string($str);
		}
		return mysql_escape_string(mb_convert_encoding($str, DB_ENCODING, SCRIPT_ENCODING));
	}
	/**
	 * @name db_close
	 * @desc データベースクローズ
	 * @return なし
	 **/
	function db_close()
	{
		mysql_close($this->_conn);
	}
	/**
	 * @name check_field_name
	 * @desc キーワードに一致するフィールド名をエスケープする
	 * @param $name フィールド名
	 * @return なし
	 **/
	function check_field_name($name)
	{
		if (($name == "case")||($name == "type")||($name == "check")||($name == "limit")) {	// エスケープが必要
			return '`' . $name . '` ';
		}
		return $name;
	}
	/**
	 * @name make_where
	 * @desc 条件の配列からSQLのWHERE文を作成する
	 * @param $where 条件の配列
	 * @return 作成したWHERE文
	 **/
	function make_where($where)
	{
		$count = 0;
		$cond = "";
		if (isset($where)) {
			foreach ($where as $key => $val) {
				$field = $this->check_field_name($val["field"]);
				if ($count > 0) {
					if (isset($val['relation'])) {
						$cond .= " " . $val['relation'] . " ";
					} else {
						$cond .= " and ";
					}
				}
				if (isset($val['nest'])) {
					$cond .= "(" . $this->make_where($val['nest']) . ")";
				} else {
					if (($val['cond'] == 'in')||($val['cond'] == 'not in')) {
						if (isset($val['value'])) {
							if (is_array($val['value'])) {
								$cond .= $field . ' ' . $val['cond'] . " (" . $this->escape_string(implode($val['value'], ",")) . ")";
							} else {
								$cond .= $field . ' ' . $val['cond'] . " (" . $this->escape_string($val['value']) . ")";
							}
						}
						if (isset($val['select'])) {
							$sql = $this->make_select($val['select']["table"], $val['select']["where"], $val['select']["order"], $val['select']["page"], $val['select']["fields"]);
							$cond .= $field . ' ' . $val['cond'] . " (" . $sql . ")";
						}
					} else if ($val['cond'] == 'any') {	// pgsqlのみ
						$cond .= "'" . $this->escape_string($val['value']) . "'=any(" . $field . ")";
					} else if ($val['cond'] == 'all') {	// pgsqlのみ
						$cond .= "'" . $this->escape_string($val['value']) . "'=all(" . $field . ")";
/*
					} else if ($val['cond'] == 'is') {
						$cond .= $field . ' is ' . $val['value'];
*/
					} else {
						if ($val['cond'] == "") {
							$op = "=";
						} else {
							$op = $val['cond'];
						}
						if (isset($val['expression'])) {
							if (isset($val["field"])) {
								$cond .= $field . ' ' . $op . " " . $this->escape_string($val['expression']);
							} else {
								$cond .= $val['expression'];
							}
						} else {
							if ($val['value'] === "now()") {
								$cond .= $field . ' ' . $op . " sysdate()";
							} else {
								if (is_array($val['value'])) {
									if ($op == "=") {
										$rel = " or ";
									} else {
										$rel = " and ";
									}
									$cond .= "(";
									$n = 0;
									foreach ($val['value'] as $val2) {
										if ($n) {
											$cond .= $rel;
										}
										$cond .= $field . ' ' . $op . " '" . $this->escape_string($val2) . "'";
										$n++;
									}
									$cond .= ")";
								} else {
									$cond .= $field . ' ' . $op . " '" . $this->escape_string($val['value']) . "'";
								}
							}
						}
					}
				}
				$count++;
			}
		}
		return $cond;
	}
	/**
	 * @name insert_data
	 * @desc データの挿入
	 * @param $table 挿入するテーブル名
	 * @param $data 挿入するデータ配列（配列のキーが項目名、値がデータ）
	 * @return 実行結果
	 **/
	function insert_data($table, $data, $raw=false)
	{
		$value = '';
		$field = '';
		foreach ($data as $key => $val) {
			if ($value != "") {
				$value .= ",";
				$field .= ",";
			}
			$field .= $this->check_field_name($key);
			if (isset($val)) {
				if (is_array($val)) {
					if (count($val)) {
						$value .= "'{{";
						$cnt = 0;
						foreach ($val as $key2 => $val2) {
							if ($cnt > 0) {
								$value .= ",";
							}
							$value .= '"' . $this->escape_string($val2) . '"';
							$cnt++;
						}
						$value .= "}}'";
					} else {
						$value .= "NULL";
					}
				} else {
					if ($val === 'now()') {	// 現在日時
						$value .= "sysdate()";
					} else {
						$value .= "'" . $this->escape_string($val, $raw) . "'";
					}
				}
			} else {
				$value .= "NULL";
			}
		}
		if ($value != "") {
			$sql = "insert into `" . $table . "` (" . $field . ") values (" . $value . ")";
			@file_put_contents(dirname(__FILE__)."/log/".date("Ymd")."_insert.txt", $sql."\n", FILE_APPEND);
			if ($this->db_exec($sql, !$raw)) {
				$sql = "select last_insert_id() as id";
				$rt = $this->search_sql($sql);
				if ($rt["count"] > 0) {
					return $rt["data"][0]["id"];
				}
			}else{
				return false;
			}
		}
		return true;
	}
	/**
	 * @name update_data
	 * @desc データの更新
	 * @param $table 更新するテーブル名
	 * @param $data 更新するデータ配列（配列のキーが項目名、値がデータ）
	 * @param $where 更新するデータを特定するWHERE条件
	 * @return 実行結果
	 **/
	function update_data($table, $data, $where, $raw=false)
	{
		$value = '';
		foreach ($data as $key => $val) {
			if ($value != "") {
				$value .= ",";
			}
			$field = $this->check_field_name($key);
			if (is_array($val)) {
				if (count($val)) {
					$value .= $field . "='{{";
					$cnt = 0;
					foreach ($val as $key2 => $val2) {
						if ($cnt > 0) {
							$value .= ",";
						}
						$value .= '"' . $this->escape_string($val2, $raw) . '"';
						$cnt++;
					}
					$value .= "}}'";
				} else {
					$value .= $field . "=NULL";
				}
			} else if ($val === 'now()') {	// 現在日時に更新
				$value .= $field . "=sysdate()";
			//	$value .= $field . "=" . $val;
			} else if ($val === 'inc()') {	// 値をインクリメント（固有機能）
				$value .= $field . "=" . $val . '+1';
			} else if ($val === '') {
				$value .= $field . "=''";
			} else {
				$value .= $field . "='" . $this->escape_string($val, $raw) . "'";
			}
		}
		// WHERE作成
		$cond = $this->make_where($where);
		if ($value != "") {
			if ($cond != "") {
				$sql = "update " . $table . " set " . $value . " where " . $cond;
			} else {
				$sql = "update " . $table . " set " . $value;
			}
			return $this->db_exec($sql, !$raw);
		}
		return false;
	}
	/**
	 * @name delete_data
	 * @desc データの削除
	 * @param $table 削除するテーブル名
	 * @param $where 削除するデータを特定するWHERE条件
	 * @return 実行結果
	 **/
	function delete_data($table, $where)
	{
		// WHERE作成
		$cond = $this->make_where($where);
		if ($cond != "") {
			$sql = "delete from " . $table . " where " . $cond;
			return $this->db_exec($sql);
		}
		return false;
	}
	/**
	 * @name count_data
	 * @desc 条件に一致するデータの件数を得る
	 * @param $table カウントするテーブル名
	 * @param $where カウントするデータを特定するWHERE条件
	 * @return 該当データ件数
	 **/
	function count_data($tables, $where)
	{
		$data = array('count' => 0);
		if (is_array($tables)) {
			foreach ($tables as $v) {
				if ($v["join"]) {	// JOINのテーブル
					$join .= " " . $v["join"] . " " . $v["table"];
					if ($v["col"]) {	// 両方が同名のフィールド
						//$join .= " on " . $table . "." . $v["col"] . "=" . $v["table"] . "." . $v["col"];
						$join .= " using(" . $v["col"] . ")";
					} else if ($v["col1"] && $v["col2"]) {	// 別名のフィールド
						$join .= " on " . $v["col1"] . "=" . $v["col2"];
					}
				} else if ($v["table"]) {	// メインのテーブル
					$table = $v["table"];
				}
			}
		} else {
			$join = "";
			$table = $tables;
		}
//		$sql = "select count(*) from " . $table;
		$sql = "select count(*) from " . $table . $join;
		$cond = $this->make_where($where);
		if ($cond != "") {
			$sql .= " where " . $cond;
		}
		$ret = $this->db_exec($sql);
		if ($ret) {
			if (mysql_num_rows($ret)) {
				$row = @mysql_fetch_row($ret);
				$data['count'] = $row[0];
			}
		} else {
			$data['count'] = 0;
			$data['error'] = 1;
			$data['message'] = mysql_error($this->_conn);
		}
		return $data;
	}

	/**
	 * @name make_select
	 * @desc SELECT文の作成
	 * @param $table 検索するテーブル名
	 * @param $where 検索するデータを特定するWHERE条件
	 * @param $order データの出力順（省略可）
	 * @param $page 出力するデータの位置と件数（省略すると全件）
	 * @param $fields 取得するフィールド名（省略すると全フィールド）
	 * @return sql文
	 **/
	function make_select($tables, $where, $order=NULL, $page=NULL, $fields=NULL)
	{
		if (is_array($tables)) {
			foreach ($tables as $v) {
				if ($v["join"]) {	// JOINのテーブル
					$join .= " " . $v["join"] . " " . $v["table"];
					if ($v["col"]) {	// 両方が同名のフィールド
						//$join .= " on " . $table . "." . $v["col"] . "=" . $v["table"] . "." . $v["col"];
						$join .= " using(" . $v["col"] . ")";
					} else if ($v["col1"] && $v["col2"]) {	// 別名のフィールド
						$join .= " on " . $v["col1"] . "=" . $v["col2"];
					}
				} else if ($v["table"]) {	// メインのテーブル
					$table = $v["table"];
				}
			}
		} else {
			$join = "";
			$table = $tables;
		}
		if (!$table) {	// テーブル未指定
			echo "No Tables";
			return "";
		}
		if ($fields) {
			if (is_array($fields)) {
				$sql = "select " . join(",", $fields) . " from " . $table . $join;
			} else {
				$sql = "select " . $fields . " from " . $table . $join;
			}
		} else {
			$sql = "select * from " . $table . $join;
		}
		$cond = $this->make_where($where);
		if ($cond != "") {
			$sql .= " where " . $cond;
		}
		if (!is_null($order)) {
			$sql .= " order by ";
			$count = 0;
			foreach ($order as $key => $val) {
				if ($count > 0) {
					$sql .= ",";
				}
				$sql .= $val['field'];
				if (isset($val['desc'])) {
					$sql .= " desc";
				}
				$count++;
			}
		}
		if (!is_null($page)) {
			if (!isset($page['offset'])) {
				$page['offset'] = '0';
			}
		//	$sql .= " offset " . $page['offset'] . " limit " .  $page['limit'];
			$sql .= " limit " .  $page['offset'] . "," . $page['limit'];
		}
		return $sql;
	}
	/**
	 * @name search_data
	 * @desc データの検索
	 * @param $table 検索するテーブル名
	 * @param $where 検索するデータを特定するWHERE条件
	 * @param $order データの出力順（省略可）
	 * @param $page 出力するデータの位置と件数（省略すると全件）
	 * @param $fields 取得するフィールド名（省略すると全フィールド）
	 * @return 実行結果
	 **/
	function search_data($table, $where, $order=NULL, $page=NULL, $fields=NULL, $raw=false)
	{
		$sql = $this->make_select($table, $where, $order, $page, $fields);
		return $this->search_sql($sql, $raw);
	}

	/**
	 * @name search_sql
	 * @desc データの検索(SQL直接実行)
	 *	複数テーブルを参照したり、副問い合わせを使用する場合に、直接SQLを実行する。
	 * @return 実行結果
	 **/
	function search_sql($sql, $raw=false)
	{
		$ret = $this->db_exec($sql);
		if ($ret) {
			$data = array();
			$count = @mysql_num_rows($ret);
			for ($row = 0; $row < $count; $row++) {
				$data[$row] = mysql_fetch_assoc($ret);
				if (!$raw) {
					foreach ($data[$row] as $key => $val) {
						if (DB_ENCODING == SCRIPT_ENCODING) {
							//
						} else {
							$val = mb_convert_encoding($val, SCRIPT_ENCODING, DB_ENCODING);
						}
						// 配列データを展開(実データに「{{～}}」が含まれるとデータが破壊されるので注意)
						if (preg_match('/^{{(.+)}}/', $val, $match)) {
							$ary = explode(",", $match[1]);
							if ($ary) {
								$data[$row][$key] = $ary;
								foreach ($data[$row][$key] as $key2 => $val2) {
									if (preg_match('/"(.+)"/', $val2, $match)) {
										$data[$row][$key][$key2] = $match[1];
									}
								}
							}
						} else {
							$data[$row][$key] = $val;
						}
					}
				}
			}
			$result['count'] = $count;
			$result['data'] = $data;
			return $result;
		} else {
			$data['count'] = 0;
			$data['error'] = 1;
			$data['message'] = mysql_error($this->_conn);
		}
		return $data;
	}
}
?>
