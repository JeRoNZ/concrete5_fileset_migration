<?php
$list = new FileSetList();
$sets = $list->get(100);
foreach ($sets as $set){
	/* @var $set FileSet */
	$item = array('ID'=>$set->getFileSetID(),'Name'=>$set->getFileSetName());
	$files = $set->getFiles();
	$setfiles = array();
	foreach ($files as $file){
		/* @var $file File */
		$f = array(
			'URL'=>$file->getVersion()->getURL(),
			'Name'=>$file->getVersion()->getTitle(),
			'Desc' =>$file->getVersion()->getDescription()
		);
		$setfiles[] = $f;
	}
	if (count($files)){
		$item['Files'] = $setfiles;
	}
	$out[] = $item;
}

header('Content-type: application/json;charset="utf8"');
echo json_encode($out);
die();