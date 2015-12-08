<?php
/**
 * ------1. 数据库备份（导出）------------------------------------------------------------
//分别是主机，用户名，密码，数据库名，数据库编码
$db = new Backups ( 'localhost', 'root', 'root', 'test', 'utf8' );
// 参数：备份哪个表(可选),备份目录(可选，默认为backup),分卷大小(可选,默认2000，即2M)
$db->backup ();
 * ------2. 数据库恢复（导入）------------------------------------------------------------
//分别是主机，用户名，密码，数据库名，数据库编码
$db = new Backups ( 'localhost', 'root', 'root', 'test', 'utf8' );
//参数：sql文件
$db->restore ( './backup/20120516211738_all_v1.sql');
 *----------------------------------------------------------------------
 */

class Backups {
	private $db;
	private $ds = "\n";
	public $sqlEnd = ";";
	public $sqldir;
	/**
	 * @param string $host	host
	 * @param string $username	用户
	 * @param string $password	密码
	 * @param string $database	数据库
	 * @param string $charset 字符
	 */
	function __construct($host = 'localhost', $username = 'root', $password = 'root', $database = 'hehe', $charset = 'utf8'){
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->charset =$charset;
		@set_time_limit(0);
		@ob_end_flush();
		$this->db = new mysqli($this->host, $this->username, $this->password, $this->database);
		$this->db or die ("数据库连接失败".$this->db->connect_error);
		$this->db->query("set names {$charset}");
	}

	/**
	 * 数据分卷备份,
	 * @param string $tablename	传入的数据表
	 * @param string $dir	保存的路径
	 * @param string $size	分卷的大小
	 * @return bool
	 */
	function backupsData($tablename = '', $dir='', $size=''){

		$dir = $dir ? $dir : './backup/';

		if(! is_dir ( $dir )){
			mkdir ($dir, 0777, true) or die ('创建文件夹失败');
		}

		$BackupsFileDir = DIRECTORY_SEPARATOR.date("YmdHis").DIRECTORY_SEPARATOR;

		$size = $size ? $size : 2048;

		$sql = '';

		$filename = date("YmdHis") . 'all';

		$p = 1;

		if($tablename =='' || $tablename == 'all'){

			if(!$tables = $this->db->query( "show table status from " . $this->database )){

				$this->_showMsg("读取数据库失败 ");

				exit ( 0 );
			}

			$sql.= $this->_retrieve();

			$tables = $this->db->query('SHOW TABLES');

			while($table = $tables->fetch_array()){

				$tablename = $table[0];

				$sql .= $this->_insert_table_structure($tablename);

				$data = $this->db->query("SELECT * FROM " . $tablename);

				$num_fields = $data->field_count;

				while($record = $data->fetch_array()){

					$sql .= $this->_insert_record($tablename, $num_fields, $record);

					if( strlen($sql) >= $size * 1000 ){

						$file = $filename . "_v" . $p .".sql";

						if(!$this->_write_file ($sql, $file, $dir, $BackupsFileDir)){
							return false;
						}

						$p++;

						$sql = '';
					}
				}
			}

			if($sql !=''){

				$file = $filename .'_v' . $p . ".sql";

				if(!$this->_write_file($sql, $file, $dir, $BackupsFileDir)){

					return false;
				}
			}
		}else{

			if(is_string($tablename)){
				$tables[0] = $tablename;
			}else{
				return false;
			}
			foreach($tables as $table){

				$sql .= $this->_retrieve();

				$sql .= $this->_insert_table_structure($table);

				$data = $this->db->query("SELECT * FROM ".$table);

				$num_fields = $data->field_count;

				while($record = $data->fetch_array()){

					$sql .= $this->_insert_record($table, $num_fields, $record);

					if( strlen($sql) >= $size*1000 ){

						$file = $filename . '_v' . $p . '.sql';
						if(!$this->__write_file($sql, $file, $dir, $BackupsFileDir)){
							$this->_showMsg('写入文件失败', true);
							return false;
						}

						$p++;
						$sql ='';
					}
				}
			}

			if($sql != ''){
				$file = $filename .'_v' . $p. '.sql';
				if(!$this->_write_file($sql, $file, $dir, $BackupsFileDir)){
					$this->_showMsg('写入文件失败', true);
					return false;
				}
			}
		}
		$this->_showMsg("存入成功");
	}

	/**
	 * @param $sql	需要存储的sql字符串
	 * @param $filename	写入的文件夹名
	 * @param $dir	备份放入的路径
	 * @param $BackupsFileDir 分卷的文件夹
	 * @return bool
	 */
	private function _write_file($sql, $filename, $dir, $BackupsFileDir){

		$dir = $dir ? $dir.$BackupsFileDir: './backup/'.$BackupsFileDir;

		if(!is_dir( $dir )){
			mkdir( $dir, 0777, true);
		}
		$filename = $dir.$filename;
		$re = true;
		if(! @$fp = fopen($filename, "w+")){
			$re = false;
		}
		if(! @fwrite($fp, $sql)){
			$re = false;
		}
		if(! @fclose($fp)){
			$re = false;
		}
		return $re;
	}

	/**
	 * @param $table 表名
	 * @param $num_fields 行的总数
	 * @param $record	所有数据
	 * @return string 备份的字段
	 */
	private function _insert_record($table, $num_fields, $record){
		$insert = '';
		$comma = "";
		$insert .= "INSERT INTO `" . $table . "` VALUES(";
		for($i = 0;$i<$num_fields;$i++){
			$insert .= ($comma . "'" . $this->db->real_escape_string( $record[$i] ) . "'");
			$comma = ",";
		}
		$insert .= ");" .$this->ds;
		return $insert;
	}

	/**
	 * @param $table 表名
	 */
	private function _insert_table_structure($table){
		$sql = '';
		$sql .= '--' . $this->ds;
		$sql .= '-- 表的结构' . $table .$this->ds;
		$sql .= '--' . $this->ds .$this->ds;

		$sql .= "DROP TABLE IF EXISTS `" . $table . '`' . $this->sqlEnd . $this->ds;
		$res = $this->db->query("SHOW CREATE TABLE `" . $table . '`');
		$row = $res->fetch_array();
		$sql .= $row[1] . $this->ds;
		$sql .= $this->ds;
		$sql .= '--' . $this->ds;
		$sql .= '-- 转存表中的数据' . $table . $this->ds;
		$sql .= '--' . $this->ds;
		$sql .= $this->ds;
		return $sql;
	}

	/**
	 * 备份文件内容头信息
	 */
	private function _retrieve(){
		$value = '';
		$value .= '--' . $this->ds;
		$value .= '-- Mysql database dump' . $this->ds;
		$value .= '-- Created by Backups class,Power By Tiger.' . $this->ds;
		$value .= '--' . $this->ds;
		$value .= '-- 主机:' . $this->host . $this->ds;
		$value .= '-- 日期:' . date('Y') . '年' . date('m') . '月' . date('d') . '日' . date('H:i') . $this->ds;
		$value .= $this->ds;
		$value .= '--' . $this->ds;
		$value .= '--' . $this->ds;
		$value .= '-- 数据库: `' . $this->database . '`' . $this->ds;
		$value .= '--' . $this->ds . $this->ds;
		$value .= '-- -------------------------------------------------------';
		$value .= $this->ds . $this->ds;
		return $value;
	}

	/**
	 * @param $msg	错误/成功信息
	 * @param bool|false $err
	 */
	private function _showMsg($msg, $err = false){
		$err = $err ? "<span class='err'>ERROR:</span>" : '';
		echo "<p class='dbDebug'>".$err.$msg."</p>";
		flush();
	}

	/**
	 * ------------------------------------------数据导入----------------------------------------
	 */

	/**
	 * 传入sql文件的文件夹路径
	 * @param $FileDir	文件夹路径
	 * @return bool
	 */
	function restore($FileDir){
		if(!file_exists($FileDir)){
			$this->_showMsg("传入的文件不存在", true);
			exit ();
		}

		$this->_lock( $this->database );

		$files = $this->_dirFiles($FileDir);

		foreach ($files as $fileName){
			$sqlfile = $FileDir.DIRECTORY_SEPARATOR.$fileName;
			$import = $this->_import($FileDir);
			if(!$import){
				//$this->_showMsg("文件可能损坏", true);
				return false;
			}
		}
		return true;
	}

	/**
	 * 获取传入的目录下的所有文件
	 * @param $dir	传入的目录
	 * @return array|bool
	 */
	private function _dirFiles($dir){
		$files = array();
		if( $handle = opendir($dir) ){
			while( false !== ($file = readdir($handle)) ){
				if($file !='.' && $file != ".."){
					$files[] = $file;
				}
			}
			return $files;
		}else{
			return false;
		}
	}

	/**
	 * 获取数据文件的字符
	 * @param $sqlfile	文件的路径
	 * @return bool
	 */
	private function _import($sqlfile){
		//$sqls = array();
		$f = fopen( $sqlfile, "rb" );
		$create_table = '';
		while( ! feof($f) ){
			$line = fgets( $f );
			if(!preg_match('/;/', $line) || preg_match( '/ENGINE=/', $line )){
				$create_table .= $line;
				if(preg_match('/ENGINE=/', $create_table)){
					$this->_insert_into($create_table);
					$create_table = '';
				}
				continue;
			}
			$this->_insert_into($line);
		}
		fclose($f);
		return true;
	}

	/**
	 * 执行sql语句
	 * @param $sql	sql
	 * @return bool
	 */
	private function _insert_into($sql){
		if(! $sql = $this->db->query( trim($sql) )){
			$this->msg .= mysqli_connect_error($sql);
			return false;
		}
	}

	/**
	 * 锁定数据库,防止备/导出错
	 * @param $tablename 表明
	 * @param string $op
	 * @return bool
	 */
	private function _lock($tablename, $op = "WRITE"){
		if($this->db->query( "lock tables " . $tablename ." " . $op)){
			return true;
		}else{
			return false;
		}
	}

	function __destruct(){
		if($this->db){
			$this->db->query("unlock tables");
			$this->db->close();
		}
	}
}


//$backups = new Backups();
//不传参数,备份所有表
//$backups->backupsData('','','');
//还原文件夹下的所有sql文件
//$backups->restore('E:\phpStudy\WWW\backup\20151203162358');
?>