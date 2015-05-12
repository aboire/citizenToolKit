<?php
class ListAction extends CAction
{
	public function run($organizationId) {
		$controller=$this->getController();

		$jobList = Job::getJobsList($organizationId);
	  
		if(Yii::app()->request->isAjaxRequest){
			$controller->renderPartial("jobList", array("jobList" => $jobList, "id" => $organizationId));
		} else {
			$controller->render("jobList", array("jobList" => $jobList, "id" => $organizationId));
		}
	}
}