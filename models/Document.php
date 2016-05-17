<?php 
class Document {

	const COLLECTION = "documents";

	const IMG_BANNIERE 			= "banniere";
	const IMG_PROFIL 			= "profil";
	const IMG_LOGO 				= "logo";
	const IMG_SLIDER 			= "slider";
	const IMG_MEDIA 			= "media";
	const IMG_PROFIL_RESIZED 	= "profil-resized";
	const IMG_PROFIL_MARKER 	= "profil-marker";

	const CATEGORY_PLAQUETTE 	= "Plaquette";

	const DOC_TYPE_IMAGE 		= "image";
	const DOC_TYPE_CSV		= "text/csv";

	const GENERATED_IMAGES_FOLDER 		= "thumb";
	const GENERATED_ALBUM_FOLDER		= "album";
	const FILENAME_PROFIL_RESIZED 	  	= "profil-resized.png";
	const FILENAME_PROFIL_MARKER 	  	= "profil-marker.png";
	const GENERATED_THUMB_PROFIL 	  	= "thumb-profil";
	const GENERATED_MARKER		 	  	= "marker";

	/**
	 * get an project By Id
	 * @param type $id : is the mongoId of the project
	 * @return type
	 */
	public static function getById($id) {
	  	return PHDB::findOne( self::COLLECTION,array("_id"=>new MongoId($id)));
	}

	public static function getWhere($params) {
	  	return PHDB::find( self::COLLECTION,$params);
	}

	protected static function listMyDocumentByType($userId, $type, $contentKey, $sort=null){
		$params = array("id"=> $userId,
						"type" => $type,
						"contentKey" => new MongoRegex("/".$contentKey."/i"));
		$listDocuments = PHDB::findAndSort( self::COLLECTION,$params, $sort);
		return $listDocuments;
	}
	// TODO BOUBOULE - TO DELETE ONLY ONE DEPENDENCE WITH getListDocumentsByContentKey
	protected static function listMyDocumentByContentKey($userId, $contentKey, $docType = null, $sort=null)	{	
		$params = array("id"=> $userId,
						"contentKey" => new MongoRegex("/".$contentKey."/i"));
		
		if (isset($docType)) {
			$params["doctype"] = $docType;
		}

		$listDocuments = PHDB::findAndSort( self::COLLECTION,$params, $sort);
		return $listDocuments;
	}

	public static function listDocumentByCategory($collectionId, $type, $category, $sort=null) {
		$params = array("id"=> $collectionId,
						"type" => $type,
						"category" => new MongoRegex("/".$category."/i"));
		$listDocuments = PHDB::findAndSort( self::COLLECTION,$params, $sort);
		return $listDocuments;	
	}
	
	/**
	 * save document information
	 * @param $params : a set of information for the document (?to define)
	*/
	public static function save($params){
		//$id = Yii::app()->session["userId"];
		if(!isset($params["contentKey"])){
			$params["contentKey"] = "";
		}
		

	    $new = array(
			"id" => $params['id'],
	  		"type" => $params['type'],
	  		"folder" => $params['folder'],
	  		"moduleId" => $params['moduleId'],
	  		"doctype" => Document::getDoctype($params['name']),	
	  		"author" => $params['author'],
	  		"name" => $params['name'],
	  		"size" => (int) $params['size'],
	  		'created' => time()
	    );


	    if(isset($params["category"]) && !empty($params["category"]))
	    	$new["category"] = $params["category"];
	    if(isset($params["contentKey"]) && !empty($params["contentKey"])){
	    	$new["contentKey"] = $params["contentKey"];
	    }

	    PHDB::insert(self::COLLECTION,$new);
	    //Generate image profil if necessary
	    if (substr_count(@$new["contentKey"], self::IMG_PROFIL)) {
	    	self::generateProfilImages($new);
	    }
	    if (substr_count(@$new["contentKey"], self::IMG_SLIDER)) {
	    	self::generateAlbumImages($new, self::GENERATED_IMAGES_FOLDER);
	    }
	    return array("result"=>true, "msg"=>Yii::t('document','Document saved successfully'), "id"=>$new["_id"],"name"=>$new["name"]);	
	}

	/**
	* get the type of a document
	* @param strname : the name of the document
	*/
	public static function getDoctype($strname){

		$supported_image = array(
		    'gif',
		    'jpg',
		    'jpeg',
		    'png'
		);

		$doctype = "";
		$ext = strtolower(pathinfo($strname, PATHINFO_EXTENSION)); // Using strtolower to overcome case sensitive
		if (in_array($ext, $supported_image)) {
			$doctype = "image";
		}else{
			$doctype = $ext;
		}
		return $doctype;
	}

	/** TODO BOUBOULE 
	*	TO DELETE --- NOT CORRECT BECAUSE OF CONTENTKEY WHICH IS A COMPLEX SEARCH WHEN IT COULD SIMPLE
	* 	Still present in city/detailAction, and survey/entryAction then impact on the rest of documents !!!
	* END TODO
	 * get the list of documents depending on the id of the owner, the contentKey and the docType
	 * @param String $id The id of the owner of the image could be an organization, an event, a person, a project... 
	 * @param String $contentKey The content key is composed with the controllerId, the action where the document is used and a type
	 * @param String $docType The docType represent the type of document (see DOC_TYPE_* constant)
	 * @param array $limit represent the number of document by type that will be return. If not set, everything will be return
	 * @return array a list of documents + URL sorted by contentkey type (IMG_PROFIL...)
	 */
	public static function getListDocumentsByContentKey($id, $contentKey, $docType=null, $limit=null){
		$listDocuments = array();
		$sort = array( 'created' => -1 );
		$explodeContentKey = explode(".", $contentKey);
		$listDocumentsofType = Document::listMyDocumentByContentKey($id, $explodeContentKey[0], $docType, $sort);
		foreach ($listDocumentsofType as $key => $value) {
			$toPush = false;
			if(isset($value["contentKey"]) && $value["contentKey"] != ""){
				$explodeValueContentKey = explode(".", $value["contentKey"]);
				$currentType = (string) $explodeValueContentKey[2];
				if (isset($explodeContentKey[1])) {
					if($explodeContentKey[1] == $explodeValueContentKey[1]){
						if (! isset($limit)) {
							$toPush = true;
						} else {
							if (isset($limit[$currentType])) {
								$limitByType = $limit[$currentType];
								$actuelNbCurrentType = isset($listDocuments[$currentType]) ? count($listDocuments[$currentType]) : 0;
								if ($actuelNbCurrentType < $limitByType) {
									$toPush = true;
								}
							} else {
								$toPush = true;
							}
						}
					}
				} else {
					$toPush = true;
				}
			}
			if ($toPush) {
				$imageUrl = Document::getDocumentUrl($value);
				if (! isset($listDocuments[$currentType])) {
					$listDocuments[$currentType] = array();
				} 
				$value['imageUrl'] = $imageUrl;
				array_push($listDocuments[$currentType], $value);
			}
		}

		return $listDocuments;
	}
	
	protected static function listMyDocumentByIdAndType($id, $type, $contentKey= null, $docType = null, $sort=null)	{	
		$params = array("id"=> $id,
						"type" => $type);
		if (isset($contentKey) && $contentKey != null) 
			$params["contentKey"] = $contentKey;
		if (isset($docType)) 
			$params["doctype"] = $docType;
		$listDocuments = PHDB::findAndSort( self::COLLECTION,$params, $sort);
		return $listDocuments;
	}

	public static function getListDocumentsByIdAndType($id, $type, $contentKey=null, $docType=null, $limit=null){
		$listDocuments = array();
		$sort = array( 'created' => -1 );
		$listDocumentsofType = Document::listMyDocumentByIdAndType($id, $type, $contentKey, $docType, $sort);
		foreach ($listDocumentsofType as $key => $value) {
			$toPush = false;
			if(isset($value["contentKey"]) && $value["contentKey"] != ""){
				$currentContentKey = $value["contentKey"];
				if (! isset($limit)) {
					$toPush = true;
				} else {
					if (isset($limit[$currentContentKey])) {
						$limitByType = $limit[$currentContentKey];
						$actuelNbCurrentType = isset($listDocuments[$currentContentKey]) ? count($listDocuments[$currentContentKey]) : 0;
						if ($actuelNbCurrentType < $limitByType)
							$toPush = true;
					} else {
						$toPush = true;
					}
				}
			} else {
					$toPush = true;
			}
			if ($toPush) {
				if ($document["moduleId"]=="communevent"){
					$pathImage = Yii::app()->params['communeventBaseUrl']."/".$document["folder"];
					
				}
				else{
					$imageUrl = "/".Yii::app()->params['uploadUrl'].$document["moduleId"]."/".$document["folder"];
				}
				$imageUrl .= "/".$document["name"];

				if (! isset($listDocuments[$currentContentKey])) {
					$listDocuments[$currentContentKey] = array();
				} 
				$value['imageUrl'] = $imageUrl;
				$value['size'] = self::getHumanFileSize($value["size"]);
				array_push($listDocuments[$currentContentKey], $value);
			}
		}
		return $listDocuments;
	}
	/** author clement.damiens@gmail.com
	 * Controle space storage of each entity
	 * @param string $id The id of the owner of document
	 * @param string $type The type of the owner of document
	 * @param string $docType The kind of document research
	 * @return size of storage used to stock
	 */
	public static function storageSpaceByIdAndType($id, $type,$docType){
		$params = array("id"=> $id,
						"type" => $type);
		if (isset($docType)) 
			$params["doctype"] = $docType;
		$c = Yii::app()->mongodb->selectCollection(self::COLLECTION);
		$result = $c->aggregate( array(
						array('$match' => $params),
						array('$group' => array(
							'_id' => $params,
							'sumDocSpace' => array('$sum' => '$size')))
						));
		if (@$result["ok"]) 
			$spaceUsed = @$result["result"][0]["sumDocSpace"];
		return $spaceUsed;

	}
	/** author clement.damiens@gmail.com
	 * Return boolean if entity is authorized to stock
	 * @param string $id The id of the owner of document
	 * @param string $type The type of the owner of document
	 * @param string $docType The kind of document research
	 * @return size of storage used to stock
	 */
	public static function authorizedToStock($id, $type,$docType){
		$storageSpace = self::storageSpaceByIdAndType($id, $type,self::DOC_TYPE_IMAGE);
		$authorizedToStock=true;
		if($storageSpace > (20*1048576))
			$authorizedToStock=false;
		return $authorizedToStock;
	}
	/**
	 * @See getListDocumentsByContentKey. 
	 * @return array Return only the Url of the documents ordered by contentkey type
	 */
	public static function getListDocumentsURLByContentKey($id, $contentKey, $docType=null, $limit=null){
		$res = array();
		$listDocuments = self::getListDocumentsByContentKey($id, $contentKey, $docType, $limit);
		foreach ($listDocuments as $contentKey => $documents) {
			foreach ($documents as $document) {
				if (! isset($res[$contentKey])) {
					$res[$contentKey] = array();
				} 
				array_push($res[$contentKey],$document["imageUrl"]);
			}
		}
		return $res;
	}
	
	/**
	* remove a document by id
	* @return
	*/
	public static function removeDocumentById($id){
		return PHDB::remove(self::COLLECTION, array("_id"=>new MongoId($id)));
	}
	/**
	* remove a document from communevent by objId
	* @return
	*/
	public static function removeDocumentCommuneventByObjId($id){
		//Suppression de l'image dans la collection cfs.photosimg.filerecord
		PHDB::remove("cfs.photosimg.filerecord", array("_id"=>$id));
		//Suppression du document
		return PHDB::remove(self::COLLECTION, array("objId"=>$id));
	}

	/**
	* upload the path of an image
	* @param itemId is the id of the item that we want to update
	* @param itemType is the type of the item that we want to update
	* @param path is the new path of the image
	* @return
	*/
	public static function setImagePath($itemId, $itemType, $path, $contentKey){
		$tabImage = explode('.', $contentKey);

		if(in_array(Document::IMG_PROFIL, $tabImage)){
			return PHDB::update($itemType,
	    					array("_id" => new MongoId($itemId)),
	                        array('$set' => array("imagePath"=> $path))
	                    );
		}
	}

	/**
	* get a list of images with a key depending on limit
	* @param itemId is the id of the item that we want to get images
	* @param itemType is the type of the item that we want to get images
	* @param limit an array containing couple with the imagetype and the numbers of images wanted (see IMG_* for available type)
	* @return return an array of type and urls of a document
	*/
	public static function getImagesByKey($itemId, $itemType, $limit) {
		$imageUrl = "";
		$res = array();

		foreach ($limit as $key => $aLimit) {
			$sort = array( 'created' => -1 );
			$params = array("id"=> $itemId,
						"type" => $itemType,
						"contentKey" => new MongoRegex("/".$key."/i"));
			$listImagesofType = PHDB::findAndSort( self::COLLECTION,$params, $sort, $aLimit);

			$arrayOfImagesPath = array();
			foreach ($listImagesofType as $id => $document) {
	    		$imageUrl = Document::getDocumentUrl($document);
	    		array_push($arrayOfImagesPath, $imageUrl);
			}
			$res[$key] = $arrayOfImagesPath;
		}
		
		return $res;
	}

	/**
	* get the last images with a key
	* @param itemId is the id of the item that we want to get images
	* @param itemType is the type of the item that we want to get images
	* @param key is the type of image we want to get
	* @return return the url of a document
	*/
	public static function getLastImageByKey($itemId, $itemType, $key){
		$imageUrl = "";
		$sort = array( 'created' => -1 );
		$params = array("id"=> $itemId,
						"type" => $itemType,
						"contentKey" => new MongoRegex("/".$key."/i"));
		
		$listImagesofType = PHDB::findAndSort( self::COLLECTION,$params, $sort, 1);
		
		foreach ($listImagesofType as $key => $value) {
    		$imageUrl = Document::getDocumentUrl($value);
		}
		return $imageUrl;
	}

	/**
	 * Get the list of categories available for the id and the type (Person, Organization, Event..)
	 * @param String $id Id to search the categories for
	 * @param String $type Collection Type 
	 * @return array of available categories (String)
	 */
	public static function getAvailableCategories($id, $type) {
		$params = array("id"=> $id,
						"type" => $type);
		$sort = array("category" => -1);
		$listCategory = PHDB::distinct(self::COLLECTION, "category", $params);
		
		return $listCategory;

	}

	public static function getHumanFileSize($bytes, $decimals = 2) {
      $sz = 'BKMGTP';
      $factor = floor((strlen($bytes) - 1) / 3);
      return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    public static function clean($string) {
       $string = preg_replace('/  */', '-', $string);
       $string = strtr($string,'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ','aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY'); // Replaces all spaces with hyphens.
       return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
    }

    public static function getDocumentUrl($document){
    	return self::getDocumentFolderUrl($document)."/".$document["name"];
    }

    public static function getDocumentFolderUrl($document){
	    if ($document["moduleId"]=="communevent")
		    $folderUrl = Yii::app()->params['communeventUrl'];
		else
			$folderUrl = "/".Yii::app()->params['uploadUrl'].$document["moduleId"];
    	$folderUrl .= "/".$document["folder"];
    	return $folderUrl;
    }

    public static function getDocumentPath($document){
    	return self::getDocumentFolderPath($document).$document["name"];
    }

    public static function getDocumentFolderPath($document){
    	return Yii::app()->params['uploadDir'].$document["moduleId"]."/".$document["folder"]."/";
    }

    public static function generateProfilImages($document) {
    	$dir = $document["moduleId"];
    	$folder = $document["folder"];

		//The images will be stored in the /uploadDir/moduleId/ownerType/ownerId/thumb (ex : /upload/communecter/citoyen/1242354235435/thumb)
		$upload_dir = Yii::app()->params['uploadDir'].$dir.'/'.$folder.'/'.self::GENERATED_IMAGES_FOLDER;
        if(file_exists ( $upload_dir )) {
            CFileHelper::removeDirectory($upload_dir."bck");
            rename($upload_dir, $upload_dir."bck");
        }
        mkdir($upload_dir, 0775);
        
     	$imageUtils = new ImagesUtils(self::getDocumentPath($document));
    	$destPathThumb = $upload_dir."/".self::FILENAME_PROFIL_RESIZED;
    	$imageUtils->resizeImage(50,50)->save($destPathThumb);
		
		$destPathMarker = $upload_dir."/".self::FILENAME_PROFIL_MARKER;
    	$markerFileName = self::getEmptyMarkerFileName(@$document["type"], @$document["subType"]);
    	if ($markerFileName) {
    		$srcEmptyMarker = self::getPathToMarkersAsset().$markerFileName;
    		$imageUtils->createMarkerFromImage($srcEmptyMarker)->save($destPathMarker);
    	}
        
        //Remove the bck directory
        CFileHelper::removeDirectory($upload_dir."bck");
	}
	// Resize initial image for album size 
	// param type array $document
	// param string $folderAlbum where Image is upload
	public static function generateAlbumImages($document,$folderAlbum=null) {
    	$dir = $document["moduleId"];
    	$folder = $document["folder"];
		if($folderAlbum==self::GENERATED_IMAGES_FOLDER){
			$destination='/'.self::GENERATED_IMAGES_FOLDER;
			$maxWidth=200;
			$maxHeight=200;
			$quality=100;
		} else{
			$destination="";
			$maxWidth=1100;
			$maxHeight=700;
			$quality=50;
		}
		//The images will be stored in the /uploadDir/moduleId/ownerType/ownerId/thumb (ex : /upload/communecter/citoyen/1242354235435/thumb)
		$upload_dir = Yii::app()->params['uploadDir'].$dir.'/'.$folder.$destination; 
		if(!file_exists ( $upload_dir )) {       
			mkdir($upload_dir, 0777);
		}
		//echo "iciiiiiii/////////////".$upload_dir;
		$path=self::getDocumentPath($document);
		list($width, $height) = getimagesize($path);
		if ($width > $maxWidth || $height >  $maxHeight){
     		$imageUtils = new ImagesUtils($path);
    		$destPathThumb = $upload_dir."/".$document["name"];
    		if($folderAlbum==self::GENERATED_IMAGES_FOLDER)
    			$imageUtils->resizeImage($maxWidth,$maxHeight)->save($destPathThumb);
    		else
    			$imageUtils->resizePropertionalyImage($maxWidth,$maxHeight)->save($destPathThumb,$quality);
    	}
	}
	
	/**
	 * Return the url of the generated image 
	 * @param String $id Identifier of the object to retrieve the generated image
	 * @param String $type Type of the object to retrieve the generated image
	 * @param String $generatedImageType Type of generated image See GENERATED_*
	 * @param String $subType used for organization (NGO, business)
	 * @return String containing the URL of the generated image of the type 
	 */
	public static function getGeneratedImageUrl($id, $type, $generatedImageType, $subType = null) {
		$sort = array( 'created' => -1 );
		$params = array("id"=> $id,
						"type" => $type,
						"contentKey" => new MongoRegex("/".self::IMG_PROFIL."/i"));
		$listDocuments = PHDB::findAndSort( self::COLLECTION,$params, $sort, 1);

		$generatedImageExist = false;
		if ($lastProfilImage = reset($listDocuments)) {
			$documentPath = self::getDocumentFolderPath($lastProfilImage).'/thumb/';
			if ($generatedImageType == self::GENERATED_THUMB_PROFIL) {
				$documentPath = $documentPath.self::FILENAME_PROFIL_RESIZED;
			} else if ($generatedImageType == self::GENERATED_MARKER) {
				$documentPath = $documentPath.self::FILENAME_PROFIL_MARKER;
			}
			$generatedImageExist = file_exists($documentPath);
		}

		//If there is an existing profil image
		if ($generatedImageExist) {
			$documentUrl = self::getDocumentFolderUrl($lastProfilImage).'/thumb/';
			if ($generatedImageType == self::GENERATED_THUMB_PROFIL) {
				$res = $documentUrl.self::FILENAME_PROFIL_RESIZED;
			} else if ($generatedImageType == self::GENERATED_MARKER) {
				$res = $documentUrl.self::FILENAME_PROFIL_MARKER;
			}
		//Else the default image is returned
		} else {
			if ($generatedImageType == self::GENERATED_MARKER) {
				$markerDefaultName = str_replace("empty", "default", self::getEmptyMarkerFileName($type, $subType));
				//$res = "/communecter/assets/images/sig/markers/icons_carto/".$markerDefaultName;
				//remove the "/ph/" on the assersUrl if there
				$homeUrlRegEx = "/".str_replace("/", "\/", Yii::app()->homeUrl)."/";
				$assetsUrl = preg_replace($homeUrlRegEx, "", @Yii::app()->controller->module->assetsUrl,1);
				$res = "/".$assetsUrl."/images/sig/markers/icons_carto/".$markerDefaultName;
			} else {
				$res = "";
			}
		}
		return $res;
	}

	private static function getEmptyMarkerFileName($type, $subType = null) {
		$markerFileName = "";

		switch ($type) {
			case Person::COLLECTION :
				$markerFileName = "citizen-marker-empty.png";
				break;
			case Organization::COLLECTION :
				if ($subType == "NGO") 
					$markerFileName = "ngo-marker-empty.png";
				else if ($subType == "LocalBusiness") 
					$markerFileName = "business-marker-empty.png";
				else 
					$markerFileName = "ngo-marker-empty.png";
				break;
			case Event::COLLECTION :
				$markerFileName = "event-marker-empty.png";
				break;
			case Project::COLLECTION :
				$markerFileName = "project-marker-empty.png";
				break;
			case City::COLLECTION :
				$markerFileName = "city-marker-empty.png";
				break;
		}

		return $markerFileName;
	}

	private static function getPathToMarkersAsset() {
		return dirname(__FILE__).DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."..".
				DIRECTORY_SEPARATOR."communecter".DIRECTORY_SEPARATOR."assets".DIRECTORY_SEPARATOR.
				"images".DIRECTORY_SEPARATOR."sig".DIRECTORY_SEPARATOR."markers".DIRECTORY_SEPARATOR.
				"icons_carto".DIRECTORY_SEPARATOR;
	}

	public static function retrieveAllImagesUrl($id, $type, $subType = null) {
		$res = array();
		//images
		$profil = self::getLastImageByKey($id, $type, self::IMG_PROFIL);
		$profilThumb = self::getGeneratedImageUrl($id, $type, self::GENERATED_THUMB_PROFIL);
		$marker = self::getGeneratedImageUrl($id, $type, self::GENERATED_MARKER);
		$res["profilImageUrl"] = $profil;
		$res["profilThumbImageUrl"] = $profilThumb;
		$res["profilMarkerImageUrl"] = $marker;
		return $res;
	}



	public static function getImageByUrl($urlImage, $path, $nameImage) {
		
		// Ouvre un fichier pour lire un contenu existant
		$current = file_get_contents($urlImage);
		// Écrit le résultat dans le fichier
		$file = "../../modules/cityData/".$nameImage;
		file_put_contents($file, $current);

		
	}


	public static function uploadDocument($dir,$folder=null,$ownerId=null,$input,$rename=false, $pathFile, $nameFile) {
		$upload_dir = Yii::app()->params['uploadUrl'];
        if(!file_exists ( $upload_dir ))
            mkdir ( $upload_dir,0775 );
        
        //ex: upload/communecter
        $upload_dir = Yii::app()->params['uploadUrl'].$dir.'/';
        if(!file_exists ( $upload_dir ))
            mkdir ( $upload_dir,0775 );

        //ex: upload/communecter/person
        if( isset( $folder )){
            $upload_dir .= $folder.'/';
            if( !file_exists ( $upload_dir ) )
                mkdir ( $upload_dir,0775 );
        }

        //ex: upload/communecter/person/userId
        if( isset( $ownerId ))
        {
            $upload_dir .= $ownerId.'/';
            if( !file_exists ( $upload_dir ) )
                mkdir ( $upload_dir,0775 );
        }
        
        $allowed_ext = array('jpg','jpeg','png','gif',"pdf","xls","xlsx","doc","docx","ppt","pptx","odt");
        
        /*if(strtolower($_SERVER['REQUEST_METHOD']) != 'post')
        {
    	    return array('result'=>false,'error'=>Yii::t("document","Error! Wrong HTTP method!"));

        }*/
        

        $file_headers = @get_headers($pathFile.$nameFile);
			if($file_headers[0] == 'HTTP/1.1 404 Not Found') {
			    $exists = false;
			}
			else {
			    $exists = true;
			}

        if(!empty($pathFile) && $file_headers[0] != 'HTTP/1.1 404 Not Found'){
        	
        	
        	
        	$ext = strtolower(pathinfo($nameFile, PATHINFO_EXTENSION));
        	if(!in_array($ext,$allowed_ext)){
        		return array('result'=>false,'error'=>Yii::t("document","Only").implode(',',$allowed_ext).Yii::t("document","files are allowed!"));
    	    
        	}	
        
        	// Move the uploaded file from the temporary 
        	// directory to the uploads folder:
        	// we use a unique Id for the iamge name Yii::app()->session["userId"].'.'.$ext
            // renaming file
            $cleanfileName = Document::clean(pathinfo($nameFile, PATHINFO_FILENAME)).".".pathinfo($nameFile, PATHINFO_EXTENSION);
        	$name = ($rename) ? Yii::app()->session["userId"].'.'.$ext : $cleanfileName;
            if( file_exists ( $upload_dir.$name ) )
                $name = time()."_".$name;

            
            $pic = file_get_contents($pathFile.$nameFile, FILE_USE_INCLUDE_PATH);
            
            
        	if(isset(Yii::app()->session["userId"]) && $name && file_put_contents($upload_dir.$name , $pic)){   
        		return array('result'=>true,
                                        "success"=>true,
                                        'name'=>$name,
                                        'dir'=> $upload_dir,
                                        'size'=> Document::getHumanFileSize( filesize ( $upload_dir.$name ) ) );
        	}
        }
        return array('result'=>false,'error'=>Yii::t("document","Something went wrong with your upload!"));
	}


	/*public static function saveDocument($newpersonId, $moduleId, $pathFile, $nameFile, $dir,$folder=null,$ownerId=null,$input,$rename=false, $pathFolderImage=false) {
		if(!empty($nameImage)){
			try{
				$res = Document::uploadDocument($moduleId, self::COLLECTION, $newpersonId, "avatar", false, $pathFolderImage, $nameImage);
				if(!empty($res["result"]) && $res["result"] == true){
					$params = array();
					$params['id'] = $newpersonId;
					$params['type'] = self::COLLECTION;
					$params['moduleId'] = $moduleId;
					$params['folder'] = self::COLLECTION."/".$newpersonId;
					$params['name'] = $res['name'];
					$params['author'] = Yii::app()->session["userId"] ;
					$params['size'] = $res["size"];
					$params["contentKey"] = "profil";
					$res2 = Document::save($params);
					if($res2["result"] == false)
						throw new CTKException("Impossible de save.");

				}else{
					throw new CTKException("Impossible uploader le document.");
				}
			}catch (CTKException $e){
				throw new CTKException($e);
			}	
		}
	}*/

}
?>