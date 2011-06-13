<?php
/**
 * @package Reports
 * @subpackage Models
 * @copyright Copyright (c) 2011 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
 
/**
 * Main report model object class.
 *
 * @package Reports
 * @subpackage Models
 */
class Reports_FileTable extends Omeka_Db_Table
{
    /**
     * Finds all report files and sorts by creation date, descending.
     *
     * @return array Array of ReportsReport objects.
     */
    public function findByReportId($reportId) {
        $select = $this->getSelect()->where('report_id = ?', $reportId)->order('created DESC');
        return $this->fetchObjects($select);
    }
}