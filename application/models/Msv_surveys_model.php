<?php

/**
 * invoice data model
 *
 * @package PersonalData
 * @version 1.0
 * @copyright Schema31 s.p.a - 2015
 */
class Msv_surveys_model extends CI_Model {

    public $sur_title = null;

    public function __construct() {
        parent::__construct();
    }

    /**
     * extract full record list
     * @param int $limit
     * @param int $offset
     * @return array of objects
     */
    public function getSurveys() {

        
        $this->db->select('sur_id, sur_title');
        $this->db->from('msv_surveys');
     
        $query = $this->db->get();
        
        $res = $query->result();
        
        return $res;
    }

  

}
