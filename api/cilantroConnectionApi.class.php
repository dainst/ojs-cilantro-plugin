<?php
class cilantroConnectionApi extends server {

    private $_ompUser;

    function __construct($data, $logger, array $settings = array()) {
        parent::__construct($data, $logger, $settings);
    }

    function start() {
        $this->_loadOMP();
    }

    private function _loadOMP() {
        // where am I?
        preg_match('#(.+)\/plugins\/(.*)\/api#', dirname(__file__), $m);
        $omp_path = $m[1];
        $plugin_path = $m[2];

        // load omp
		require($omp_path . '/tools/bootstrap.inc.php');

		// Initialize the request object with a page router
		$application = Application::getApplication();
		$request = $application->getRequest();
		import('classes.core.PageRouter');
		$router = new PageRouter();
		$router->setApplication($application);
		$request->setRouter($router);

		// Initialize the locale and load generic plugins.
		AppLocale::initialize($request);

		// return info
		$this->return["system"] = $application->getName() . " " . $application->getCurrentVersion()->getVersionString();
    }

    private function _checkUser() {
        // get session
        $sessionManager =& SessionManager::getManager();
        $session =& $sessionManager->getUserSession();

        // is logged in
        if (!$session->user) {
            throw new Exception("no user logged in");
        }

        $this->_ompUser = $session->user;
        $this->log->debug('access allowed for user ' . $this->_ompUser->getUsername());
        $this->returnCode = 403;
    }

    private function _querySql($sql) {
		$monographDAO = DAORegistry::getDAO('MonographDAO');
		$records = $monographDAO->retrieve($sql);
		$result = new DAOResultFactory($records, $this, '_convertSqlResultRow');
		return $result->toArray();
    }

    function _convertSqlResultRow($row) {

        $value = @unserialize($row["setting_value"]);
        if ($value === false) {
            $value = $row["setting_value"];
        }

        return array(
            "id" => $row['press_id'],
            "key" => $row['press_key'],
            "setting_value" => $value,
            "setting_name" => $row['setting_name'],
			"locale" => $row['locale']
        );
    }

    private function _checkXml($xml) {
        $test = new SimpleXMLElement($xml);
        $this->log->debug("XML integrity check passed");
        return $xml;
    }

	private function _getNativeImportExportPlugin() {
		PluginRegistry::loadCategory('importexport', true, 0);
		$nativeImportExportPlugin = PluginRegistry::getPlugin('importexport', 'NativeImportExportPlugin');
		return $nativeImportExportPlugin;
	}

    private function _getompUser($userId = 1) {
        $userDao =& DAORegistry::getDAO('UserDAO');
        $user = $userDao->getById($userId);
        if (is_null($user)) {
            throw new Exception("User $userId not found");
        }
        return $user;
    }

    private function _getPress($pressPath) {
        $pressDao =& DAORegistry::getDAO('PressDAO');
		$press = $pressDao->getByPath($pressPath);
        if (is_null($press)) {
            $this->returnCode = 404;
            throw new Exception("Journal $pressPath not found");
        }
        $this->log->debug("got press " . $press->getPageHeaderTitle() . " ($pressPath)");
        return $press;
    }

	private function _getRoles($user, $journal) {
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$roles = array();
		$allUsersRoles = $roleDao->getByUserIdGroupedByContext($user->getId());
		foreach ($allUsersRoles[$journal->getId()] as $role) {
			$roles[] = $role->getRoleId();
		}
		return $roles;
	}

	private function _isAllowedToUpload($user, $journal) {
		$roles = $this->_getRoles($user, $journal);
		$this->log->debug("userroles: " . implode(", ", $roles));
		$allowed = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR);
		return array_intersect($roles, $allowed);
	}

	private function _publishMonograph($mId, $press) {
    	$monographDao = DAORegistry::getDAO("MonographDAO"); /* @var $monographDao MonographDAO */
		$submissionFileDao = DAORegistry::getDAO("SubmissionFileDAO"); /* @var $submissionFileDao SubmissionFileDAO */
		$representationDao = Application::getRepresentationDAO(); /* @var $representationDao RepresentationDAO */

		$monograph = $monographDao->getById($mId, $press->getId(), false); /* @var $monograph Monograph */

		$submissionFiles = $submissionFileDao->getBySubmissionId($mId);

		foreach ($submissionFiles as $file) {
			$salesType ='openAccess';
			$this->log->warning("publishing: " . $file->getFileId() . "-" . $file->getRevision());
			$approvedProof = $submissionFileDao->getRevision($file->getFileId(), $file->getRevision());
			$approvedProof->setDirectSalesPrice(0);
			$approvedProof->setSalesType($salesType);
			$approvedProof->setViewable(true);
			$submissionFileDao->updateObject($approvedProof);

			$representationsRF = $representationDao->getBySubmissionId($mId); /* @var $representationsRF DAOResultFactory */
			$representations = $representationsRF->toAssociativeArray(); /* @var $representations array */

			$representation = array_pop($representations); /* @var $representation Representation */ // we import only one
			//$approvedProof->set
			$representation->

			$this->log->warning(print_r($representation,1));
		}

	}


	private function _runImport($xml, $pressCode) {

		$nativeImportExportPlugin = $this->_getNativeImportExportPlugin();

		$press = $this->_getPress($pressCode);
		$user = $this->login();

		if (!$this->_isAllowedToUpload($user, $press)) {
			$this->returnCode = 401;
			throw new Exception("The user >>" . $user->getUsername() . "<< is not allowed to upload to >>" . $pressCode .
				"<<. He must have the role >>editor<< or >>manager<< for this journal.");
		}

		$doc = new DOMDocument();
		$doc->loadXml($xml);

		$rootTag = $doc->documentElement->nodeName;

		if ($rootTag !== "monograph") {
			$this->returnCode = 401;
			throw new Exception("The OJS-Cilantro-Plugin only supports import.xml's with the root node <monograph> and it's <{$rootTag}>.");
		}

		@set_time_limit(0);

		// catch DB-errors (and other uncaught php-level-errors)
		define("DONT_DIE_ON_ERROR", true);
		set_error_handler(function($errno, $errstr, $errfile, $errline) {
			throw new Exception("Import failed: [$errno]" . $errstr);
		}, E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_WARNING & ~E_NOTICE);

		// catch xml errors
		libxml_use_internal_errors(true);

		// set up the import
		$deployment = new NativeImportExportDeployment($press, $user);

		// go
		$nativeImportExportPlugin->importSubmissions($doc, $deployment);

		// collect xml errors
		$lastError = "";
		$validationErrors = array_filter(libxml_get_errors(), function($a) {
			return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
		});
		libxml_clear_errors();
		if (count($validationErrors)) {
			foreach ($validationErrors as $error) {
				$lastError = $error;
				$this->log->warning("Validation Error: {$error->message} (line: {$error->line})");
			}
		}

		// catch ojs-handled errors
		$types = array(ASSOC_TYPE_SUBMISSION);

		foreach ($types as $type) {
			foreach ($deployment->getProcessedObjectsWarnings($type) as $objectId => $warnings) {
				foreach ($warnings as $warning) {
					$this->log->warning($warning);
				}
			}
			foreach ($deployment->getProcessedObjectsErrors($type) as $objectId => $errors) {
				foreach ($errors as $error) {
					$lastError = $error;
					$this->log->warning("Error: " . $error);
				}
			}
		}

		// return unsuccess and cleanup is critical
		if ($lastError != "") {
			foreach (array_keys($types) as $assocType) {
				$deployment->removeImportedObjects($assocType);
			}
			throw new Exception("Import failed: $lastError");
		}

		// return result
		$this->return['published_monographs'] = $deployment->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION);

//		// publish them
//		foreach ($deployment->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION) as $mId) {
//			$this->_publishMonograph($mId, $press);
//		}

		restore_error_handler();

		$this->log->debug("Import successful!");
	}

    public function pressInfo() {
		$this->return['data'] = array();
		$sql = "select
				  presses.press_id,
				  path as press_key,
				  setting_name,
				  setting_value,
				  press_settings.locale as locale
				from
				  presses
					left join press_settings on presses.press_id = press_settings.press_id
				where
					setting_name in ('supportedLocales', 'name', 'description')
				order by
				  path;";
		foreach ($this->_querySql($sql) as $row) {
			if (!isset($this->return['data'][$row['key']])) {
				$this->return['data'][$row['key']] = array(
					"id" => $row['id'],
					"path" => $row['key'],
				);
			}
			if ($row['locale'] != "") {
				if (!isset($this->return['data'][$row['key']][$row['setting_name']])) {
					$this->return['data'][$row['key']][$row['setting_name']] = array();
				}
				if ($row['setting_value'] != "") {
					$this->return['data'][$row['key']][$row['setting_name']][$row['locale']] = $row['setting_value'];
				}
			} else {
				$this->return['data'][$row['key']][$row['setting_name']] = $row['setting_value'];
			}
		}
    }

    function import() {
        $xml = $this->_checkXml($this->data["%"]);
		$pressCode = $this->data["/"][0];
        $this->_runImport($xml, $pressCode);
    }

    function login() {
        $this->returnCode = 401;
        if (!isset($_SERVER["HTTP_OMPAUTHORIZATION"])) {
            throw new Exception("no login credentials given");
        }
        $credentials = explode(":", $_SERVER["HTTP_OMPAUTHORIZATION"]);
        if (count($credentials) != 2) {
            throw new Exception("login credentials not ok");
        }

        $username = base64_decode($credentials[0]);
        $password = base64_decode($credentials[1]);

        $this->log->debug("credentials: $username : password");

        $user = Validation::login($username, $password, $reason, false);

        if (!$user) {
            throw new Exception("Could not login with $username. $reason");
        }

        $this->returnCode = 200;
        return $user;
    }


	function zenon() {
		$dao = new DAO();
		$url = Config::getVar('general', 'base_url') . '/index.php/';
		$zid = (isset($this->data["/"]) and isset($this->data["/"][0]))
			? preg_replace('/\D/', '', $this->data["/"][0])
			: false;
		$oao = false; // select open access files only?
		$sql = "select
				  replace(a_s.setting_value,'&dfm','') as zenonid,
				  concat('$url', j.path, '/article/view/', a.submission_id) as url
				from
				  published_submissions as p_a
					left join submissions as a on a.submission_id = p_a.submission_id
					left join submission_settings as a_s on p_a.submission_id = a_s.submission_id
					left join presses as j on j.press_id = a.context_id
				where
					setting_name in('pub-id::other::zenon','zenon_id')
				  and setting_value not in ('', '(((new)))')
				  and a.status = 3
				  and j.enabled = 1" .
			($zid ? " and a_s.setting_value = '$zid'" : '');
		$res = $dao->retrieve($sql);
		$this->return["publications"] = $res->getAssoc();
		$res->Close();
	}


    function finish() {

    }
}


?>
