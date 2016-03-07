<?php

class ImportInMongoAction extends CAction
{
    public function run()
    {

    	$paramsCollection = array("_id"=>new MongoId($_POST['idCollection']));
        $fieldsCollection = array("key");
        $infoCollection = Import::getMicroFormats($paramsCollection, $fieldsCollection);
        
        if($infoCollection[$_POST['idCollection']]["key"] == "Organizations")
            $collection = Organization::COLLECTION;
        else if($infoCollection[$_POST['idCollection']]['key'] == "Projets")
        	$collection = Project::COLLECTION;

        $paramsForJson = array("jsonImport"=> $_POST["jsonImport"],
                            "jsonError"=> $_POST["jsonError"],
                            "nameFile" => $_POST["nameFile"],
                            "collection" => $collection);

        Import::createOrUpdateJsonForImport($paramsForJson);

        $params = array();
        return Rest::json($params);
    }
}

?>