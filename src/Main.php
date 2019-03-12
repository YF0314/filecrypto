<?php

/**
 * @author @Xere_yukky
 */

require 'config/define.php';

class Filecrypto
{
	function start($file,$passphrase,$plustime = null)
	{
		// $file = $this->abc($file)
		if ($this->abc($file)) {
		$data = base64_encode(file_get_contents($file));
		$method = 'AES-256-CBC';

		$ivLength = openssl_cipher_iv_length($method);
		$iv = openssl_random_pseudo_bytes($ivLength);

		$options = 0;
		$encrypted = openssl_encrypt($data, $method, $passphrase, $options, $iv);
		$decrypted = openssl_decrypt($encrypted, $method, $passphrase, $options, $iv);
		#echo base64_encode($decrypted);
		$iv = base64_encode($iv);
		if ($plustime == null) {
			$plustime = 3000; // 3000sec (50min)
		}
		$time = time()+$plustime;
		// Database insert
		try {
			$dsn = 'mysql:host='.DB_Server.';dbname='.DB_Name.';charset=utf8';
    		$db = new PDO($dsn,DB_User,DB_Password);
    		$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    		$sql = 'INSERT INTO tests (pass,unixtime,iv) VALUES (:pass,:unix,:iv)';
			$prepare = $db->prepare($sql);
			# $prepare->bindValue(':file',base64_encode($encrypted), PDO::PARAM_STR);
			$prepare->bindValue(':pass',$passphrase, PDO::PARAM_STR);
			$prepare->bindValue(':unix',$time, PDO::PARAM_STR);
			$prepare->bindValue(':iv',$iv, PDO::PARAM_STR);
			$prepare->execute();
    		$id = $db->lastInsertId();
		} catch (PDOException $e) {
		$return = array('status'=> 500);
			return $return;
		}
		$return = array('status'=> 200 , 'file' => base64_encode($encrypted) , 'id' => $id );
		return $return;
		}else{
		$return = array('status'=> 503);
		return $return;
		}
	}
	function end($file,$pass,$id){
		if (empty($file) || empty($pass) || empty($id)) {
			return true;
		}
		try {
			$dsn = 'mysql:host='.DB_Server.';dbname='.DB_Name.';charset=utf8';
    		$db = new PDO($dsn,DB_User,DB_Password);
    		$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    		$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = 'SELECT * FROM tests WHERE id=:id';
			$prepare = $db->prepare($sql);
			$prepare->bindValue(':id',$id, PDO::PARAM_STR);
			$prepare->execute();
			$dbdata = $prepare->fetchAll(PDO::FETCH_OBJ);
		} catch (PDOException $e) {
			echo "Sorry".$e->getMessage();
		}
		foreach ($dbdata as $row) {
			$db_pass="{$row->pass}";
			$token="{$row->iv}";
			$unix="{$row->unixtime}";
		}
		if ($unix < time() && $pass == $db_pass) {
			$method = 'AES-256-CBC';
			$options = 0;
			$decrypted = openssl_decrypt(base64_decode($file), $method, $pass, $options, base64_decode($token));
			return $decrypted;
		}else{
			return true;
		}
	}
	function abc($file)
	{
		if ($this->checksize($file) === true) {
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$type = $finfo->file($file);
			if ($type == 'image/jpeg' || $type == 'image/png') {
				# JPG,PNG 対応
				return true;
			}else{
				# echo "対応していないファイルフォーマットです。".$type;
				return false;
			}
		}else{
			return false;
		}
	}
	function checksize($file)
	{
		if (filesize($file)) {
		if (filesize($file) < 20000) {
			// echo filesize($file);
			return true;
		}else{
			// echo filesize($file);
			return false;
		}
		}
	}
}
