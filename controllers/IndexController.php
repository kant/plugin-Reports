<?php
/**
 * @package Reports
 * @subpackage Controllers
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
 
/**
 * Index controller
 *
 * @package Reports
 * @subpackage Controllers
 */
class Reports_IndexController extends Omeka_Controller_Action
{
    /**
     * Sets the model class for the Reports controller.
     */
    public function init()
    {
        $this->_modelClass = 'ReportsReport';
    }
    
    /**
     * Displays the browse page for all reports.
     */
    public function browseAction()
    {
        if(!is_writable(REPORTS_SAVE_DIRECTORY)) {
            $this->flash('Warning: The directory '.REPORTS_SAVE_DIRECTORY.
                         ' must be writable by the server for reports to be'.
                         ' generated.', Omeka_Controller_Flash::ALERT);
        }
        
        if(ini_get('allow_url_fopen') != 1) {
            $this->flash('Warning: The PHP directive "allow_url_fopen" is set'.
                         ' to false.  You will be unable to generate QR Code'.
                         ' reports.', Omeka_Controller_Flash::ALERT);
        }
        
        $reports = $this->getTable('ReportsReport')->findAllReports();
        foreach($reports as $report) {
            $id = $report->id;
            $creator = $report->creator;
            
            $userName = $this->getTable('Entity')->find($creator)->getName();
            $query = unserialize($report->query);
            $params = reports_convertSearchFilters($query);
            $count = $this->getTable('Item')->count($params);
            
            $reportsDisplay[] = array(
                'reportObject' => $report,
                'userName' => $userName,
                'count' => $count);
        }
        $this->view->reports = $reportsDisplay;
    }
    
    /**
     * Edits the filter for a report.
     */
    public function queryAction()
    {
        $report = $this->findById();
        
        if(isset($_GET['search'])) {
            $report->query = serialize($_GET);
            $report->save();
            $this->redirect->goto('index');
        } 
        else {
            $queryArray = unserialize($report->query);
            // Some parts of the advanced search check $_GET, others check
            // $_REQUEST, so we set both to be able to edit a previous query.
            $_GET = $queryArray;
            $_REQUEST = $queryArray;
        }
        
    }
    
    /**
     * Shows details and generated files for a report.
     */
    public function showAction()
    {
        $report = $this->findById();
        
        $reportFiles = $report->getFiles();
        
        $formats = reports_getOutputFormats();
        
        $this->view->formats = $formats;
        
        $this->view->reportsreport = $report;
        $this->view->reportFiles = $reportFiles;
    }
    
    /**
     * Spawns a background process to generate a new report file.
     */
    public function generateAction()
    {
        if (!is_writable(REPORTS_SAVE_DIRECTORY)) {
            // Disallow generation if the save directory is not writable
            $this->flash('The directory '.REPORTS_SAVE_DIRECTORY.
                         ' must be writable by the server for reports to be'.
                         ' generated.',
                         Omeka_Controller_Flash::GENERAL_ERROR);
                         
        } else {
            $report = $this->findById();
        
            $reportFile = new ReportsFile();
            $reportFile->report_id = $report->id;
            $reportFile->type = $_GET['format'];
            $reportFile->status = ReportsFile::STATUS_STARTING;
        
            // Send the base URL to the background process for QR Code
            if($reportFile->type == 'PdfQrCode') {
                $reportFile->options = serialize(array('baseUrl' => WEB_ROOT));
            }
        
            $reportFile->save();
        
            $command = get_option('reports_php_path').' '.$this->_getBootstrapFilePath()." -r $reportFile->id";
            $reportFile->pid = $this->_fork($command);
            $reportFile->save();
        
            $this->redirect->gotoRoute(array('id' => "$report->id",
                                             'action' => 'show'),
                                             'reports-id-action');
        }
    }
    
    /**
     * Returns the path to the background bootstrap script.
     *
     * @return string Path to bootstrap
     */
    private function _getBootstrapFilePath()
    {
        return REPORTS_PLUGIN_DIRECTORY
             . DIRECTORY_SEPARATOR 
             . 'bootstrap.php';
    }
    
    /**
     * Launch a background process, returning control to the foreground.
     * 
     * @link http://www.php.net/manual/en/ref.exec.php#70135
     * @return int The background process' PID
     */
    private function _fork($command) {
        return exec("$command > /dev/null 2>&1 & echo $!");
    }
}