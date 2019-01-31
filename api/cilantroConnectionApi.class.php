<?php
class cilantroConnectionApi extends server {

    private $_ojsUser;
    private $_locale;

    function __construct($data, $logger, array $settings = array()) {
        parent::__construct($data, $logger, $settings);
    }

    function start() {
        $this->_loadOJS();
    }

    private function _loadOJS() {
        // where am I?
        preg_match('#(.+)\/plugins\/(.*)\/api#', dirname(__file__), $m);
        $ojs_path = $m[1];
        $plugin_path = $m[2];

        // load OJS
        if (!defined("OJS_PRESENT") or !OJS_PRESENT) {
            require_once($ojs_path . '/tools/bootstrap.inc.php');
        }

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

        $this->_ojsUser = $session->user;
        $this->log->debug('access allowed for user ' . $this->_ojsUser->getUsername());
        $this->returnCode = 403;
    }

    private function _querySql($sql) {
        $articleDao = DAORegistry::getDAO('ArticleDAO');
        $records = $articleDao->retrieve($sql);
        $result = new DAOResultFactory($records, $this, '_convertSqlResultRow');
        return $result->toArray();
    }

    function _convertSqlResultRow($row) {

        $value = @unserialize($row["setting_value"]);
        if ($value === false) {
            $value = $row["setting_value"];
        }

        return array(
            "id" => $row['journal_id'],
            "key" => $row['journal_key'],
            "setting_value" => $value,
            "setting_name" => $row['setting_name'],
			"locale" => $row['locale'],
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

    private function _getOJSUser($userId = 1) {
        $userDao =& DAORegistry::getDAO('UserDAO');
        $user = $userDao->getById($userId);
        if (is_null($user)) {
            throw new Exception("User $userId not found");
        }
        return $user;
    }

    private function _getJournal($journalPath) {
        $journalDao =& DAORegistry::getDAO('JournalDAO');
        $journal = $journalDao->getByPath($journalPath);
        if (is_null($journal)) {
            $this->returnCode = 404;
            throw new Exception("Journal $journalPath not found");
        }
        $this->log->debug("got journal " . $journal->getLocalizedPageHeaderTitle() . " ($journalPath)");
        return $journal;
    }

//    private function _importErrors($errors) {
//        foreach ($errors as $error) {
//            $this->log->warning(PKPLocale::translate($error[0], $error[1]));
//        }
//    }

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

    private function _getIssuesArticleIds($issueId) {
        $publishedArticleDAO =& DAORegistry::getDAO('PublishedArticleDAO');
        $publishedArticles = $publishedArticleDAO->getPublishedArticles($issueId);
        return $this->_getObjectIdsFromList($publishedArticles);
    }

    private function _getObjectIdsFromList($list) {
        $ids = array();
        foreach ($list as $record) {
            $ids[] = $record->getId();
        }
        return $ids;
    }

    private function _runImport($xml, $journalCode) {

        $nativeImportExportPlugin = $this->_getNativeImportExportPlugin();

        $journal = $this->_getJournal($journalCode);
        $user = $this->login();

        if (!$this->_isAllowedToUpload($user, $journal)) {
            $this->returnCode = 401;
            throw new Exception("The user >>" . $user->getUsername() . "<< is not allowed to upload to >>" . $journalCode .
            "<<. He must have the role >>editor<< or >>manager<< for this journal.");
        }

		$doc = new DOMDocument();
		$doc->loadXml($xml);

		$rootTag = $doc->documentElement->nodeName;

        if ($rootTag !== "issue") {
            $this->returnCode = 401;
            throw new Exception("The OJS-Cilantro-Plugin only supports import.xml's with the root node <issues> and it's <{$rootTag}>.");
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
		$filter = 'native-xml=>issue';
		if (in_array($doc->documentElement->tagName, array('article', 'articles'))) {
			$filter = 'native-xml=>article';
		}
		$deployment = new NativeImportExportDeployment($journal, $user);

		// go
		$nativeImportExportPlugin->importSubmissions($doc, $filter, $deployment);

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
        $types = array(ASSOC_TYPE_ISSUE, ASSOC_TYPE_SUBMISSION, ASSOC_TYPE_SECTION);

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
        $this->return['published_articles'] = $deployment->getProcessedObjectsIds(ASSOC_TYPE_SUBMISSION);
        $this->return['published_issues'] = $deployment->getProcessedObjectsIds(ASSOC_TYPE_ISSUE);

        restore_error_handler();

        $this->log->debug("Import successful!");
    }

    public function journalInfo() {
        $this->return['data'] = array();
        $sql = "select
				journals.journal_id,
				path as journal_key,
				setting_name,
				setting_value,
				journal_settings.locale as locale
			 from
			 	journals
				left join journal_settings on journals.journal_id = journal_settings.journal_id
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
        $journalCode = $this->data["/"][0];
        $this->_runImport($xml, $journalCode);
    }

    function login() {
        $this->returnCode = 401;
        if (!isset($_SERVER["HTTP_OJSAUTHORIZATION"])) {
            throw new Exception("no login credentials given");
        }
        $credentials = explode(":", $_SERVER["HTTP_OJSAUTHORIZATION"]);
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

    function finish() {

    }
}


?>
