<?php
class NewsAction extends CAction
{
	public function run() {
		
		$controller=$this->getController();
		$controller->layout = "//layouts/mainSearch";
        $controller->render( "news" );

		//return Rest::json(array("result" => true, "list" => $search));
	}
}