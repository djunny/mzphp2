<?php
!defined('ROOT_PATH') && exit('ROOT_PATH not defined.');
class singer {
	
	var $table = 'stat';
	// 
	//DB::select('stat', array('posts'=> '<=0'), array('year DESC'));
	//DB::select('stat', array('posts'=> '=0'), array('year DESC'));
	//DB::select('stat', 'posts=0 AND year<>\'\'', array('year DESC'));
	public function get($id) {
		return DB::select($this->table, array('year'=>$id), 0);
	}
	
}
?>