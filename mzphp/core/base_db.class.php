<?php
class base_db {
	
	public $table;
	private $primary_key;
	
	function __construct($table, $primary_key) {
		$this->table = $table;
		$this->primary_key = $primary_key;
	}
	
	public function update($data, $id){
		if(is_array($id)){
			return DB::update($this->table, $data, $id);
		}else{
			return DB::update($this->table, $data, array($this->primary_key=>$id));
		}
	}
	
	public function insert($data, $return_id=0){
		return DB::insert($this->table, $data, $return_id);
	}
	
	public function replace($data){
		return DB::replace($this->table, $data);
	}
	public function select($where, $order=0, $perpage=-1, $page=1){
		return DB::select($this->table, $where, $order, $perpage, $page);
	}
	public function get($id){
		return DB::select($this->table, array($this->primary_key=>$id), 0, 0);
	}
	public function delete($id){
		if(is_array($id)){
			if(isset($id[0])){
				//batch delete
				foreach($id as $_id){
					self::delete($_id);
				}
			}else{
				//delete by where
				DB::delete($this->table, $id);
			}
		}else{
			DB::delete($this->table, array($this->primary_key=>$id));
		}
	}
}

?>