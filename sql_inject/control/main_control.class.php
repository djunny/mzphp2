<?php
class main_control extends base_control{
	//cd sql_inject
	//php index.php main index
	function on_index(){
		$in = new inject('http://www.wygk.cn/cnen/EnCompHonorBig.asp?id=15');
		if($in->check()){
			core::console_log('success');
		}
	}
}

?>