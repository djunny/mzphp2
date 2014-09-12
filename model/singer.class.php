<?php
!defined('ROOT_PATH') && exit('ROOT_PATH not defined.');
class singer {
	
	var $table = 'stat';
	// 
	public function get($id) {
		return DB::select($this->table, array('year'=>$id), 0);
	}
	
}
?>