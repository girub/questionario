<?php

/**
 * Badge related controller
 * 
 * @package WebBadge
 * @version 1.0
 * @copyright Schema31 s.r.l. - 2013
 */
if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class Surveys extends CI_Controller {

 
    public function __construct() {
        parent::__construct();
        $this->load->helper('url');
        $this->load->library('session');
        $this->response = new stdClass();
        $this->response->validation = true;
        $this->response->data = null;
        $this->response->errorNum = '';
        $this->response->errorText = '';

    }
    
    
    /**
     * Welcome page - show current opertaive engage time frames and previously access
     */
    function index() {

        $this->load->model('Msv_surveys_model');
        $obj['data'] = $this->Msv_surveys_model->getSurveys();
        
        //var_dump($obj->data);die();
        
        
        $this->load->view('surveys', $obj);
      
    }


}
