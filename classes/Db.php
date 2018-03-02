<?php

class Db {
	
	private $dbh = false;
	private $query = array();
	private $data = array();
	private $table = false;
	private $where = false;
	private $action = false; // insert,update,select
	
	function connect() {
		try {
		    $this->dbh = new PDO(DB_CONNECTION . ':host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
		} 
		catch (PDOException $e) {
		    echo "Error: connection failed";
		    die();
		}
	}
	
	function insert($table=false, $data=false) {
		$this->table = $table;
		$this->data = $data;
		if ($this->table && $this->data) {
			$this->action = 'insert';
			$key = $this->table . '_' . $this->action;
			if (!isset($this->query[$key])) {
				$q = "INSERT INTO " . $this->table . " (" . implode(", ", array_keys($this->data)) . ") VALUES (" . implode(", ", array_fill(0, count($this->data), '?')) . ")";
				$this->query[$key] = $this->dbh->prepare($q);
			}
			$this->data = array_values($this->data);
			$success = $this->query[$key]->execute($this->data);
			if ($success === false) {
				return false;
			}
			else {
				return $this->lastId();
			}
		}
	}
	
	function update($table=false, $data=false, $where=false) {
		$updated = false;
		$this->table = $table;
		$this->data = $data;
		if ($this->table && $this->data) {
			$fields = array();
			foreach ($data as $field => $val) {
				$fields[] = $field . "='" . $val . "'";
			}
			$query = 'update ' . $this->table . ' set ' . implode(", ", $fields) .  ($where ? ' WHERE ' . $where : '');
			$this->query[$query] = $this->dbh->prepare($query);
			$this->query[$query]->execute();
			$updated = $this->query[$query]->execute();
		}
		return $updated;
	}
	
	function lastId() {
		return $this->dbh->lastInsertId();
	}
	
	function delete($table=false, $where=false) {
		if ($table) {
			$query = "DELETE FROM " . $table;
			if ($where) {
				$query .= " WHERE " . $where;
			}
			$this->query[$query] = $this->dbh->prepare($query);
			$this->query[$query]->execute();
		}
	}
	
	function search($table=false, $data=false) {
		$res = false;
		$this->table = $table;
		$this->data = $data;
		if ($this->table && $this->data) {
			$fields = array();
			foreach ($this->data as $field => $value) {
				$fields[] = $field . "='" . $value . "'";
			}
			$query = "SELECT * FROM " . $this->table . " WHERE " . implode(" AND ", $fields);
			$res = $this->results($query);
		}
		return $res;
	}
	
	function resultsStoredQuery($query_id=false, $data=false, $map_primary_key=true) {
		if ($query_id && $data) {
			
		}
	}
	
	function result($query=false, $map_primary_key=true, $data=array()) {
		$res = $this->results($query, $map_primary_key, $data);
		return $res ? current($res) : false;
	}
	
	function results($query=false, $map_primary_key=true, $data=array()) {
		if ($query) {
			if (!isset($this->query[$query])) {
				$this->query[$query] = $this->dbh->prepare($query);
			}
			$this->query[$query]->execute($data);
			if ($map_primary_key) {
				return array_map('reset', $this->query[$query]->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC));
			}
			else {
				return $this->query[$query]->fetchAll(PDO::FETCH_ASSOC);
			}
		}
		return false;
	}
	
	
	
}



?>