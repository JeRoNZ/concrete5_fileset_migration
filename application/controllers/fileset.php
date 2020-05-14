<?php
namespace Application\Controller;

/*
 *
 * add to application/config/app.php:
 *
return array(
	'routes' => array(
		"/ccm/fileset" => array('\Application\Controller\Fileset::view'),
	),
);
 *
 * Access code as /index.php/ccm/fileset
 *

 *
 * It is imperative that this code is run on a virgin concrete 5.8+ system, or one that has no file sets defined
 * This is because we are inserting the file sets directly into the database, using the original ID that they
 * had in the 5.6 system. This is the whole point: Any blocks etc that use a file set ID will transfer across
 * with that value intact, and we want to make sure that it exits in the 5.9+ system
 *
 */

use Concrete\Core\Routing\RedirectResponse;
use Controller;
use User;
use Database;
use Concrete\Core\File\Importer;
use Concrete\Core\File\File;
use Concrete\Core\File\Set\Set as Foo;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\Filesystem;
use Concrete\Core\Tree\Node\Type\FileFolder;
use Concrete\Core\File\FolderItemList;

use Doctrine\ORM\Mapping as ORM;
use Concrete\Core\Support\Facade\DatabaseORM as dbORM;

set_time_limit(0);

class Fileset extends Controller {
	public function view () {
		$db = Database::connection();
		/* @var $db \Concrete\Core\Database\Connection\Connection */
		$json = json_decode(file_get_contents('filesets.json'), true);

		$offset = $this->request->get('offset') ? $this->request->get('offset') : 0;
		$count = 0;

		foreach ($json as $k => $item) {
			$name = $item['Name'] ? $item['Name'] : 'Other';
			if ($offset > $k)
				continue;

/*
 * Unfortunately Doctrine/concrete/idk leaks memory, so the solution is to restart the process
 * after doing a few file sets which prevents memory usage exceeding server capacity, at leas with PHP-FPM.
 * YMMV.
 */
			if (++$count == 5) {
				return new RedirectResponse('/index.php/ccm/fileset?offset=' . ($offset + 4));
			}

			$db->execute('REPLACE INTO FileSets VALUES(?,?,?,?,?)', array(
				$item['ID'], // This is imperative
				$name,
				1,
				1,
				0
			));

			$set = Foo::getByID($item['ID']);

			$parent = $this->getParentFolder();
			$folder = $this->makeFolderIfNotExists($name, $parent);

			if (is_array($item['Files'])) {
				foreach ($item['Files'] as $file) {

					$fv = null;

					$prefix = (int) $file['Prefix'];
					if (! $prefix) {
						$prefix = null;
					} else {
						$path  = [
							DIRNAME_APPLICATION,
							'files',
							substr($file['Prefix'],0,4),
							substr($file['Prefix'],4,4),
							substr($file['Prefix'],8,4),
							$file['Filename']
						];

						$relPath = implode(DIRECTORY_SEPARATOR, $path);

						if (is_file($relPath)) {
							// File exists so get its id
							$sql = 'SELECT fID FROM FileVersions WHERE fvFilename=? AND fvPrefix=?';
							$row = $db->fetchAssoc($sql, [$file['Filename'], $prefix]);
							if ($row){
								$f = File::getByID($row['fID']);
								$fv = $f->getVersion();
							}
						}
					}

					if ($fv !== null) { // Got this one already, just add it to the set
						$set->addFileToSet($fv);

					} else {
						$filename = basename($file['URL']);
						file_put_contents($filename, file_get_contents($file['URL']));

						$imp = new Importer();

						$fv = $imp->import(basename($file['URL']), false, $folder, $prefix);
						/* @var $fv Version */

						$imp = null;

						if ($filename != $file['Name'])
							$fv->updateTitle($file['Name']);

						if ($file['Desc'])
							$fv->updateDescription($file['Desc']);

						$set->addFileToSet($fv);

						unlink($filename);
					}
				}
			}

			$set = null;
			$parent = null;
			$folder = null;
		}
	}


	private function getParentFolder () {
		$parent = FileFolder::getNodeByName('File Sets');
		if (!$parent) {
			$fs = new Filesystem();
			$root = $fs->getRootFolder();
			$parent = $fs->addFolder($root, 'File Sets');
		}

		return $parent;
	}

	private function makeFolderIfNotExists ($name, $parent) {
		// Cannot get by path, Have to filter by parent object and name
		$list = new FolderItemList();
		$list->filterByParentFolder($parent);
		$list->filterByType('file_folder');

		$results = $list->getResults();

		$child = false;
		foreach ($results as $r) {
			if ($r instanceof FileFolder) {
				/* @var $r FileFolder */
				if ($r->getTreeNodeName() == $name) {
					$child = $r;
					break;
				}
			}
		}
		if (!$child) {
			$child = FileFolder::add($name, $parent);
		}

		return $child;
	}
}