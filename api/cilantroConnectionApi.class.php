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
        return array(
            "id" => $row['journal_id'],
            "key" => $row['journal_key'],
            "setting_value" => unserialize($row["setting_value"]),
            "setting_name" => $row['setting_name']
        );
    }

    private function _checkXml($xml) {
        $test = new SimpleXMLElement($xml);
        $this->log->debug("XML integrity check passed");
        return $xml;
    }

    private function _parseXml($xml) {
        $parser = new XMLParser();
        return $parser->parseText($xml);
    }

    private function _getFrontmatterPlugin() {
        import('classes.core.PageRouter');
        $application =& PKPApplication::getApplication();
        $request = $application->getRequest();

        $router = new PageRouter();
        $router->setApplication($application);
        $request->setRouter($router);

        PluginRegistry::loadCategory('generic', false, CONTEXT_ID_NONE);
        return PluginRegistry::getPlugin('generic', 'dfm');
    }

    private function _getNativeImportExportPlugin() {
        PluginRegistry::loadCategory('importexport', true, 0);
        $nativeImportExportPlugin = PluginRegistry::getPlugin('importexport', 'NativeImportExportPlugin');
        return $nativeImportExportPlugin;
    }

    private function _saveToTempFile($data) {
        $fileName = "/tmp/" + md5(rand() + date());
        file_put_contents($fileName);
        return $fileName;
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
        $journal = $journalDao->getJournalByPath($journalPath);
        if (is_null($journal)) {
            $this->returnCode = 404;
            throw new Exception("Journal $journalPath not found");
        }
        $this->log->debug("got journal " . $journal->getLocalizedTitle() . " ($journalPath)");
        return $journal;
    }

    private function _importErrors($errors) {
        foreach ($errors as $error) {
            $this->log->warning(PKPLocale::translate($error[0], $error[1]));
        }
    }

    private function _getRoles($user, $journal) {
        $roleDao = DAORegistry::getDAO('RoleDAO');
        $roles = array();
        foreach ($roleDao->getRolesByUserId($user->getId(), $journal->getId()) as $role) {
            $roles[] = $role->getRoleId();
        }
        return $roles;
    }

    private function _isAllowedToUpload($user, $journal) {
        $roles = $this->_getRoles($user, $journal);
        $this->log->debug("userroles: " . implode(", ", $roles));
        $allowed = array(ROLE_ID_SITE_ADMIN, ROLE_ID_JOURNAL_MANAGER, ROLE_ID_EDITOR);
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

        $context = array(
            'journal' => $journal,
            'user' => $user
        );

        $doc = $this->_parseXml($xml);

        if ($doc->name !== "issues") {
            $this->returnCode = 401;
            throw new Exception("The OJS-Cilantro-Plugin only supports import.xml's with the root node <issues> and it's <{$doc->name}>.");
        }

        $errors = array();
        $issues = array();
        $articles = array();

        @set_time_limit(0);

        if (!$nativeImportExportPlugin->handleImport($context, $doc, $errors, $issues, $articles, true)) {
            $this->_importErrors($errors);
            throw new Exception("Import Failed.");
        }

        $this->return['published_articles'] = $this->_getObjectIdsFromList($articles);
        $this->return['published_issues'] = $this->_getObjectIdsFromList($issues);
        foreach ($issues as $issue) {
            $this->return['published_articles'] = array_merge($this->return['published_articles'], $this->_getIssuesArticleIds($issue->getId()));
        }

        $this->log->debug("Import Successfull!");
    }

    public function journalInfo() {
        $this->return['data'] = array();
        $sql = "select
				journals.journal_id,
				path as journal_key,
				setting_name,
				setting_value
			 from
			 	journals
				left join journal_settings on journals.journal_id = journal_settings.journal_id
			where
				setting_name in ('supportedLocales', 'title')
			order by
				path;";
        foreach ($this->_querySql($sql) as $row) {
            $this->log->warning(print_r($row,1));

            if (!isset($this->return['data'][$row['key']])) {
                $this->return['data'][$row['key']] = array(
                    "id" => $row['id'],
                    "path" => $row['key'],
                );
            }
            $row[$row['setting_name']] =  $row['setting_value'];
        }
    }

    function import() {
        $xml = $this->_checkXml($this->data["%"]);
        $journalCode = $this->data["/"][0];
        $user = "admin";
        $this->_runImport($xml, $journalCode);
    }

    function login() {
        $this->returnCode = 401;
        if (!isset($_SERVER[HTTP_OJSAUTHORIZATION])) {
            throw new Exception("no login credentials given");
        }
        $credentials = explode(":", $_SERVER[HTTP_OJSAUTHORIZATION]);
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

    function _importDfmLog($dfm) {
        $danger = false;
        foreach ($dfm->logger->log as $entry) {
            if (in_array($entry->type, array("warning", "danger", "error"))) {
                $this->log->warnings[] = $entry->text;
            }
            $this->log->log("[{$entry->type}] {$entry->text}");
        }
    }

    function frontmatters() {
        $user = $this->login();

        $dfm = $this->_getFrontmatterPlugin();
        $dfm->loadDfm();

        $command = $this->data["/"][0];
        $this->log->debug("command:  $command");
        if (!in_array($command, array("replace", "create"))) {
            throw new Exception("Frontmatter Creator Command >>$command<< unknown");
        }

        $dfm->settings->doFrontmatters = $command;

        $type = $this->data["/"][1];
        $this->log->debug("id-type: $type");
        if (!in_array($type, \dfm\processor::supportedTypes)) {
            throw new Exception("Frontmatter Creator Id-Type >>$type<< unknown");
        }

        $idlist = isset($this->data['id']) ? explode(',', $this->data['id']) : false;
        if (!$idlist or !count($idlist)) {
            throw new Exception("nothing to do. provide at least one Id is in >>id<<-Parameter");
        }

        $plugin->settings->doThumbnails = isset($this->data['thumbnails']) ? !!$this->data['thumbnails'] : false;

        $success = $dfm->startUpdateFrontpages($idlist, $type, false);
        $this->_importDfmLog($dfm);
        if (!$success) {
            throw new Exception("Frontmatter creation did not succeed.");
        }


    }

    function finish() {

    }
}


?>
