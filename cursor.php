<?php


/*
Please configure this to match your server/database setup.
*/
define("DB_HOST", "localhost");
define("DB_USER", "root");
define("DB_NAME", "");
define("DB_PASS", "");


/*

EXAMPLES
=========

### DON'T WORRY ABOUT SQL INJECTIONS UNLESS YOU ARE USING THE query FUNCTION. ###

$cursor = new Cursor;

$result = $cursor->select("users");
# returns everything in the table users.

//$result = $cursor->select("users", ["name","photo","email"]); 
# returns name, email and photo of every user.

//$result = $cursor->select("friends", ["name","photo","email"], ["friend_id"=>12345]); 
# returns name, email and photo of the friend with a friend_id of 12345

//$result = $cursor->likeSelect("follower", ["photo","email"], ["name"=>"pia"]); 
# this works very similar to the select except that it matches every whose name starts with pia..

foreach($result as $user){
	echo $user->name;
}

$id = $cursor->insert("users", ["name"=>"Alex","email"=>"example@joe.com"]); // > 0 when successful.

$id = $cursor->insertUnique("users",["name"=>"Alex","email"=>"example@joe.com"], ["name"]);
# This will insert a new column IF ONLY there is no other other person with the email example@joe.com in users.
# the third parameters tell the function that name doesn't necessarily have to be unique.
# insertUnique return "-1" if the data matches a column in the table.


if($cursor->update("users", ["name"=>"Richard"], ["id"=>5,"email"=>"example@me.com"])){
 	echo "Updated!"; 
} else { 
	echo "Failed"; 
}
# Updates name as Richard where id is 5 and email is xample@me.com, and return true if it succeds or false if otherwise.

if($cursor->delete("users",["user_id"=>5])){
	echo "I just deleted the user with an id of 5";
}

$string = $cursor->escape("Some string that may contain SQL injection such as 'this.");
# This IS ONLY necessary if you are using the query function as Cursor already protects you against SQL injections.

$result = $cursor->query("SOME SQL STATEMENTS..."); 
# Use this if you will like to supply your own custom query and return a standard Cursor result.

$cursor->connection()
# returns the connection handle.


$cursor->close();
# If you have ever used the android Cursor, you will know it's a good practise to always close
# your cursors. This will help you run non-blocking operations especially on a high traffic website.


*/

class Cursor{
	
	private $con = null;
	public $errors = array();
	
	function __construct(){ }
	
	private function connect(){
		if($this->con != null) return true;
		try {
            $this->con = new PDO('mysql:host='. DB_HOST .';dbname='. DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
            return true;
        } catch (PDOException $e) {
            $this->errors[] .= $e->getMessage();
        }
	}
	
	// use e.g. like ["!name"=>"Example name"] to match not equal to.
	public function select($table, $columns = null, $where = null, $order = null, $limit = null, $where_extra = null){
		$_cols = ""; $_cls = "";
		if($this->connect()){
			if($columns == null) $_cols = "*";
			else {
				foreach($columns as $col){
				$_cols .= ",`{$col}`";
				}
				$_cols = substr($_cols, 1);
			}
			if($where != null){
				foreach($where as $key => $value){
					$p = "="; 
					if($key[0] == "!"){
						$p = "!=";
						$key = substr($key, 1);
					}
					$_cls .= " AND `$key` {$p} :{$key}";
				}
				$_cls = " WHERE ".substr($_cls, 4);
			}
            $_cls .= $where_extra;
			if($order != null) $order = "ORDER BY {$order}";
			if($limit != null) $limit = "LIMIT {$limit}";
			$sql = $this->con->prepare("SELECT {$_cols} FROM `{$table}`{$_cls} {$order} {$limit}");
			if($where != null){
				foreach($where as $key => $value){
					if($key[0] == "!") $key = substr($key, 1);
					$sql->bindValue(":{$key}", $value, PDO::PARAM_STR);
				}
			}
			$sql->execute();
			if($result = $sql->fetchAll(PDO::FETCH_OBJ)) return $result;
		}
		return false;
	}
	
	public function likeSelect($table, $columns = null, $where = null, $order = null, $limit = null, $where_extra = null){
		$_cols = ""; $_cls = "";
		if($this->connect()){
			if($columns == null) $_cols = "*";
			else {
				foreach($columns as $col){
				$_cols .= ",`{$col}`";
				}
				$_cols = substr($_cols, 1);
			}
			if($where != null){
				foreach($where as $key => $value){
					$_cls .= " AND `$key` LIKE :{$key}";
				}
				$_cls = " WHERE ".substr($_cls, 4);
			}
            $_cls .= $where_extra;
			if($order != null) $order = "ORDER BY {$order}";
			if($limit != null) $order = "LIMIT {$limit}";
			$sql = $this->con->prepare("SELECT {$_cols} FROM `{$table}`{$_cls} {$order} {$limit}");
			if($where != null){
				foreach($where as $key => $value){
					if($key[0] == "!") $key = substr($key, 1);
					$sql->bindValue(":{$key}", $value."%", PDO::PARAM_STR);
				}
			}
			$sql->execute();
			if($result = $sql->fetchAll(PDO::FETCH_OBJ)) return $result;
		}
		return false;
	}
	
	public function insert($table, $data){
		if($this->connect()){
		$_cols = ""; $_vals = "";
		foreach($data as $key => $value){
			$_cols .= ",`{$key}`";
			$_vals .= ",:{$key}";
		}
		$_cols = substr($_cols, 1);
		$_vals = substr($_vals, 1);
		$sql = $this->con->prepare("INSERT INTO `{$table}` ({$_cols}) VALUES ({$_vals})");
		
		foreach($data as $key => $value){
			$sql->bindValue(":{$key}", $value, PDO::PARAM_STR);
		}
		
		$sql->execute();
		$id = $this->con->lastInsertId();
		return $id;
		}
	}
	
	public function insertUnique($table, $data, $exception = []){
		$data2 = [];
		foreach($data as $key => $val){
			if(!in_array($key, $exception)){
				$data2 = array_merge($data2, [$key=>$val]);
			}
		}
		$test = $this->select($table, null, $data2);
		if(empty($test)){
			return $this->insert($table, $data);
		} else {
			return -1;
		}
		return 0;
	}
	
	// use e.g. like ["!name"=>"Example name"] to update where not equal to.
	public function update($table, $data, $where = null){
		if($this->connect()){
		$_cols = ""; $_cls = "";
		foreach($data as $key => $value){
			$_cols .= ",`{$key}` = :{$key}";
		}
		$_cols = substr($_cols, 1);
		if(!empty($where)){
			foreach($where as $key => $value){
				$p = "="; 
				if($key[0] == "!"){
					$p = "!=";
					$key = substr($key, 1);
				}
				$_cls .= ",`$key` {$p} :_{$key}";
			}
			$_cls = " WHERE ".substr($_cls, 1);
		}
		$sql = $this->con->prepare("UPDATE `{$table}`  SET {$_cols} {$_cls}");
		foreach($data as $key => $value){
			$sql->bindValue(":{$key}", $value, PDO::PARAM_STR);
		}
		if(!empty($where)){
			foreach($where as $key => $value){
				if($key[0] == "!") $key = substr($key, 1);
				$sql->bindValue(":_{$key}", $value, PDO::PARAM_STR);
			}
		}
		return $sql->execute();
		}
        return false;
	}
	
	public function delete($table, $where){
		if($this->connect()){
		$_cols = "";
		foreach($where as $key => $value){
			$p = "="; 
			if($key[0] == "!"){
				$p = "!=";
				$key = substr($key, 1);
			}
			$_cols .= ",`{$key}` {$p} :{$key}";
		}
		
		### Comment our the following lines to implment a trash system.
		// Trashing before removing row
		// $sqr = $this->select($table, null, $where);
		// $this->insert("trash", ["content" => @json_encode($sqr)]);
		
		// Removing row.
		$sql = $this->con->prepare("DELETE FROM `{$table}` WHERE {$_cols}");
		foreach($where as $key => $value){
			$sql->bindValue(":{$key}", $value, PDO::PARAM_STR);
		}
		$sql->execute();
		}
	}
	
	function close(){
		if($this->con != null){
		$this->con->close();
		$this->con = null;
		}
	}
	
	function query($s){
		if($this->connect()){
			$sql = $this->con->prepare($s);
			$sql->execute();
			return $sql->fetchAll(PDO::FETCH_OBJ);
		}
		return false;
	}
	
	function escape($s){
		if($this->connect()){
			$s = $this->con->quote($s);
		}
		return $s;
	}
	
	function connection(){
		return $this->con;
	}
	
}


?>
