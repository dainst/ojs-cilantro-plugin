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
            "locales" => unserialize($row["setting_value"])
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
            $this->log->warning($error[0]);
        }
    }

    private function _runImport($xml, $journalCode) {

        $nativeImportExportPlugin = $this->_getNativeImportExportPlugin();

        $context = array(
            'journal' => $this->_getJournal($journalCode),
            'user' => $this->_getOJSUser()
        );

        $doc = $this->_parseXml($xml);

        $errors = array();
        $issues = array();
        $articles = array();

        @set_time_limit(0);

        if (!$nativeImportExportPlugin->handleImport($context, $doc, $errors, $issues, $articles, false)) {
            $this->_importErrors($errors);
            throw new Exception("Import Failed.");
        }

        $this->log->debug("Import Successfull!");
    }




    public function journalInfo() {
        $this->return['data'] = array();
        $sql = "select
				journals.journal_id,
				path as journal_key,
				setting_value
			 from
			 	journals
				left join journal_settings on journals.journal_id = journal_settings.journal_id
			where
				setting_name = 'supportedLocales'
			order by
				path;";
        foreach ($this->_querySql($sql) as $row) {
            $this->return['data'][$row['key']] = $row;
        }
    }

    function import() {
        // get xml
        $xml = $this->_checkXml($this->data["%"]);
        $journalCode = $this->data["/"][0];

        // import
        $user = "admin";
        $nativeImportExportPlugin = $this->_runImport($xml, $journalCode);


        //la,la,la,la

    }






    function createFrontmatters() {

        // create frontmatters & thumbnails

    }

    function finish() {

    }
}


?>