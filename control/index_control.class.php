<?php
class index_control extends base_control{
	
	function __construct(&$conf) {
		parent::__construct($conf);
	}
	
	function on_index(){
		V::assign_value('a', '1');
		//, 'data/index.htm'
		V::display($this, 'index.htm');
	}
}
?>