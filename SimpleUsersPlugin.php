<?php
/**
 * Main class for simple user plugin page plugin
 * 
 * @author Joe Simpson
 * 
 * @class SimpleXMLPlugin
 *
 */

namespace APP\plugins\importexport\simpleusers;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\file\TemporaryFileManager;
use PKP\plugins\ImportExportPlugin;
use PKP\security\Validation;
use PKP\userGroup\UserGroup;

 class SimpleUsersPlugin extends ImportExportPlugin {

    protected $opType;
    protected $isResultManaged = false;
    protected $result = null;

    static $ajax = false;
    static $log = [];

    static $fields = [
        'givenname:required:locale', 'familyname:locale', 'affiliation:locale', 'country',
        'email:required', 'url', 'orcid', 'biography',
        'username:required', 'gossip', 'password:required', 'date_registered', 'date_last_login', 'date_last_email', 'date_validated',
        'inline_help', 'auth_string', 'phone', 'mailing_address', 'billing_address', 'locales', 'disabled_reason',
        'user_group_ref:required', 'review_interests',
    ];

    public function getName() {
        return 'SimpleUsersPlugin';
    }

    public static function log($items) {
        if(static::$ajax) {
            static::$log[] = $items;
        } else {
            echo implode("\t", $items) . "\n";
        }
    }

    /**
     * Provide a name for this plugin
     *
     * The name will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDisplayName()
    {
        return 'Simple Users';
    }

    /**
     * Provide a description for this plugin
     *
     * The description will appear in the Plugin Gallery where editors can
     * install, enable and disable plugins.
     */
    public function getDescription()
    {
        return 'Simple user import [InvisibleDragon]';
    }

    public function esc_xml($input)
    {
        // Modified from wordpress
        $safe_text = $input; // todo: at some point check utf8

        $cdata_regex = '\<\!\[CDATA\[.*?\]\]\>';
        $regex       = <<<EOF
/
	(?=.*?{$cdata_regex})                 # lookahead that will match anything followed by a CDATA Section
	(?<non_cdata_followed_by_cdata>(.*?)) # the "anything" matched by the lookahead
	(?<cdata>({$cdata_regex}))            # the CDATA Section matched by the lookahead

|	                                      # alternative

	(?<non_cdata>(.*))                    # non-CDATA Section
/sx
EOF;

        $safe_text = (string) preg_replace_callback(
            $regex,
            static function ( $matches ) {
                if ( ! isset( $matches[0] ) ) {
                    return '';
                }

                if ( isset( $matches['non_cdata'] ) ) {
                    // escape HTML entities in the non-CDATA Section.
                    return htmlspecialchars( $matches['non_cdata'], ENT_XML1 );
                }

                // Return the CDATA Section unchanged, escape HTML entities in the rest.
                return htmlspecialchars( $matches['non_cdata_followed_by_cdata'], ENT_XML1 ) . $matches['cdata'];
            },
            $safe_text
        );
        return $safe_text;
    }

    public function display($args, $request)
    {

        $templateMgr = TemplateManager::getManager($request);
        parent::display($args, $request);

        $context = $request->getContext();
        $user = $request->getUser();
        // $deployment = $this->getAppSpecificDeployment($context, $user);
        // $this->setDeployment($deployment);

        ini_set('display_errors', 'On');

        $this->opType = array_shift($args);
        switch ($this->opType) {
            case 'index':
            case '':
                $apiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'submissions');
                $submissionsListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                    'submissions',
                    __('common.publications'),
                    [
                        'apiUrl' => $apiUrl,
                        'count' => 100,
                        'getParams' => new \stdClass(),
                        'lazyLoad' => true,
                    ]
                );
                $submissionsConfig = $submissionsListPanel->getConfig();
                $submissionsConfig['addUrl'] = '';
                $submissionsConfig['filters'] = array_slice($submissionsConfig['filters'], 1);
                $templateMgr->setState([
                    'components' => [
                        'submissions' => $submissionsConfig,
                    ],
                ]);
                $templateMgr->assign([
                    'pageComponent' => 'ImportExportPage',
                ]);

                $templateMgr->display($this->getTemplateResource('index.tpl'));

                $this->isResultManaged = true;
                break;
            case 'uploadImportXML':
                $temporaryFileManager = new TemporaryFileManager();
                $temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
                if ($temporaryFile) {
                    $json = new JSONMessage(true);
                    $json->setAdditionalAttributes([
                        'temporaryFileId' => $temporaryFile->getId()
                    ]);
                } else {
                    $json = new JSONMessage(false, __('common.uploadFailed'));
                }
                header('Content-Type: application/json');

                $this->result = $json->getString();
                $this->isResultManaged = true;

                break;
            case 'importBounce':
                $tempFileId = $request->getUserVar('temporaryFileId');

                if (empty($tempFileId)) {
                    $this->result = new JSONMessage(false);
                    $this->isResultManaged = true;
                    break;
                }

                $tab = $this->getBounceTab(
                    $request,
                    __('plugins.importexport.simpleXML.results'),
                    'importMiddle',
                    ['temporaryFileId' => $tempFileId]
                );

                $this->result = $tab;
                $this->isResultManaged = true;
                break;
            case 'importMiddle':
                $temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $user);
                $fopen = fopen($temporaryFilePath, 'r');
                $headers = fgetcsv($fopen);

                $headerOptions = [ 'null' => 'Nothing', ];
                foreach($headers as $header) {
                    $headerOptions[ 'csv:' . $header ] = 'Field: ' . $header;
                }

                $xmlData = [];
                foreach(static::$fields as $header) {
                    $parts = explode(":", $header);
                    $header = array_shift($parts);
                    $xml = [ 'key' => $header, 'options' => $headerOptions ];
                    if(in_array('required', $parts)) {
                        unset($xml['options']['null']);
                    }
                    switch($header) {
                        case 'user_group_ref':
                            $userGroups = UserGroup::query()
                                ->withContextIds([$context->getId()])
                                ->get();
                            foreach ($userGroups as $userGroup) {
                                $xml['options']['raw:' . $userGroup->getLocalizedData('name')] = 'Group: ' . $userGroup->getLocalizedData('name');
                            }
                            break;
                    }
                    $xmlData[] = $xml;
                }

                $templateMgr->assign('temporaryFileId', $request->getUserVar('temporaryFileId'));
                $templateMgr->assign('xmlData', $xmlData);
                $json = new JSONMessage(true, $templateMgr->fetch( $this->getTemplateResource('importMiddle.tpl') ));
                header('Content-Type: application/json');
                $result = $json->getString();
                $this->result = $result;
                $this->isResultManaged = true;

                break;
            case 'import':
                if (!$request->checkCSRF()) {
                    throw new \Exception('CSRF mismatch!');
                }
                $temporaryFilePath = $this->getImportedFilePath($request->getUserVar('temporaryFileId'), $user);
                
                header('Content-Type: text/xml');
                header('Content-Disposition: attachment; filename="users-to-import.xml"');

                echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
                echo '<PKPUsers xmlns="http://pkp.sfu.ca" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://pkp.sfu.ca pkp-users.xsd">' . PHP_EOL;
                echo '<users>' . PHP_EOL;

                $fopen = fopen($temporaryFilePath, 'r');
                $headers = fgetcsv($fopen);

                while (($row = fgetcsv($fopen, 1000, ",")) !== FALSE) {

                    // Make up data from csv line
                    $data = [];
                    foreach($headers as $k => $header) {
                        $data[$header] = $row[$k];
                    }

                    // Make outputabble data
                    $outdata = [];
                    foreach($_POST['xml'] as $key => $value) {

                        if($value && $value !== 'null') {
                            $val = '';
                            $valP = explode(":", $value);
                            switch($valP[0]) {
                                // namespace for value type:
                                case 'raw':
                                    $val = $valP[1];
                                    break;
                                case 'csv':
                                    $val = $data[$valP[1]];
                                    break;
                            }
                            $val = str_replace('&ndash;', '-', $val); // Fix strange encoding issue
                            $val = html_entity_decode($val);
                            
                            $outdata[$key] = $val;
                        }

                    }

                    // Now output it!
                    echo '<user>' . PHP_EOL;

                    foreach(static::$fields as $header) {
                        $parts = explode(":", $header);
                        $field = $parts[0];

                        $value = $outdata[$field];

                        if(!$value && $field == 'date_registered') {
                            $value = date('Y-m-d H:i:s');
                        }
                        if($value && $field == 'username') {
                            $value = substr($value, 0, 32);
                            $value = explode('@', $value)[0];
                        }
                        if(!$value) continue;

                        if($field == 'password') {
                            $value = Validation::encryptCredentials('', $value);
                            echo '<password is_disabled="false" must_change="false" encryption="sha1"><value>' . $value . '</value></password>';
                        } else {
                            $value = $this->esc_xml($value);
                            if(in_array('locale', $parts)) {
                                echo '<' . $field . ' locale="en">' . $value . '</' . $field . '>' . PHP_EOL;
                            } else {
                                echo '<' . $field . '>' . $value . '</' . $field . '>' . PHP_EOL;
                            }
                        }
                    }

                    echo '</user>' . PHP_EOL;

                }

                echo '</users>' . PHP_EOL;
                echo '</PKPUsers>' . PHP_EOL;

                break;
        }

        return $this->result;

    }

    public function executeCLI($scriptName, &$args)
    {

        echo "TBC";
        return false;
    }

    public function usage($scriptName)
    {
        echo __('plugins.importexport.simplexml.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n";
    }

 }