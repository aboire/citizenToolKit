<?php
class AddAttendeeSvAction extends CAction
{
    public function run(){

    	$controller=$this->getController();
    	$params = array();
    	
    	//$params["countries"] = OpenData::getCountriesList();
    	if( isset($_GET["isNotSV"])) {
            $params["isNotSV"] = true;
			$params["id"]=$_GET["eventId"];
			$params["eventName"]=$_GET["eventName"];
        }
        if(Yii::app()->request->isAjaxRequest)
			echo $controller->renderPartial("addAttendeesSV", $params, true);
    }
}