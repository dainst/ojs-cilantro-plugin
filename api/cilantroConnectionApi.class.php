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
        $result = new DAOResultFactory($records, $this, '_dummy');
        return $result->toArray();
    }

    /**
     * needed by DAOResultFactory above
     */
    function _dummy($row) {
        return array(
            "id" => $row['journal_id'],
            "key" => $row['journal_key'],
            "locales" => unserialize($row["setting_value"])
        );
    }

    public function journalInfo() {
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
            $this->return[$row['key']] = $row;
        }
    }

    function finish() {

    }
}


?>