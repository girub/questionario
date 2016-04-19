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

class Questions extends CI_Controller {

 
    public function __construct() {
        parent::__construct();

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

         $que_sur_id = 1;
        
        
        $this->load->model('Msv_questions_model');
        $obj = $this->Msv_questions_model->getQuestions($que_sur_id);
        
            
        $this->load->view('questions', $obj->data);
      
    }

    /**
     * List registered access for the current user
     */
    function badgeHistoryList() {
        $data['getConfigAction'] = 'Jqgrid_ctrl/getJqgridConfigParams/badgeHistoryList';
        $data['getDataAction'] = 'Jqgrid_ctrl/getJqgridTableData/badgeHistoryList';
        $data['getExcel'] = 'Jqgrid_ctrl/getJqgridTableData/badgeHistoryList';
        $this->load->view('templates/header', $data);
        $this->load->view('commonView/listView', $data);
        $this->load->view('templates/footer', $data);
    }

    function getConfirmationData() {

        $this->load->model('User_mdl');
        $this->load->model('Badge_mdl');
        $this->load->model('Time_shift_mdl');

        // only ajax requests are accepted
        if (!$this->input->is_ajax_request()) {
            invalidRequest();
        }

        // init the response container
        $this->response = array();
        $this->response['validation'] = TRUE;

        // check for a valid target user id
        if (!$this->User_mdl->getById($this->session->userdata('userId'))) {
            invalidRequest();
        }

        // check for open record associated to the user
        $lastOpenAccessRecordId = Badge_mdl::getLastOpenedAccessRecord($this->User_mdl->id);
        if ($lastOpenAccessRecordId) {
            // last record is open - confirmation before exit
            $this->response['currentStatus'] = 1;
            $this->response['doBadgeButtonlabel'] = lang('labelBadgeConfirmExit');
            $this->response['confirmationAskLabel'] = lang('labelBadgeHandle');
            $this->response['confirmationAskMsg'] = lang('confirmationAsk');
        } else {
            // last record is closed (or not exists) - confirmation before access
            $this->response['currentStatus'] = 0;
            $this->response['doBadgeButtonlabel'] = lang('labelBadgeConfirmAccess');
            $this->response['confirmationAskLabel'] = lang('labelBadgeHandle');
            $this->response['confirmationAsk'] = lang('confirmationAsk');
        }

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));
    }

    function getCurrentStatus() {

        $this->load->model('User_mdl');
        $this->load->model('Badge_mdl');

        // only ajax requests are accepted
        if (!$this->input->is_ajax_request()) {
            invalidRequest();
        }

        // init the response container
        $this->response = array();
        $this->response['validation'] = TRUE;

        // check for a valid target user id
        if (!$this->User_mdl->getById($this->session->userdata('userId'))) {
            invalidRequest();
        }

        // verify if badge is enabled from outside known networks
        if (!$this->config->item('badgeFromOutsideKnownNetworks') && !$this->session->userdata('knownAccessLocation')) {
            invalidRequest();
        }

        // check for open record associated to the user
        $lastOpenAccessRecordId = Badge_mdl::getLastOpenedAccessRecord($this->session->userdata('userId'));

        $this->response['currentStatus'] = ($lastOpenAccessRecordId ? 1 : 0);

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));
    }

    function doBadge() {

        $this->load->model('User_mdl');
        $this->load->model('Badge_mdl');
        $this->load->model('Group_mdl');
        $this->load->model('Office_ip_mdl');

        /*
         * Questo metodo può essere richiamato sia dalla webapp (comportamento di default) 
         * che tramite lettore biometrico.
         * Nel secondo caso, non possiamo fare affidamento sulla presenza di una sessione 
         * attiva, quindi i parametri necessari all'esecuzione dovranno essere passati 
         * nella richiesta.
         */
        $isWebAppRequest = TRUE;
        $userId = NULL;
        $action = NULL;

        $this->response = array();
        $this->response['validation'] = TRUE;

        // estraiamo lo userId
        if ($this->input->post('userId', TRUE) && strlen($this->input->post('userId', TRUE)) > 0) {
            $isWebAppRequest = FALSE;
            $userId = $this->input->post('userId', TRUE);
        } else {
            $isWebAppRequest = TRUE;
            $userId = $this->session->userdata('userId');
        }

        // userId valido?
        if (!$this->User_mdl->getById($userId)) {
            invalidRequest();
        }

        // estraiamo il gruppo di lavoro di riferimento, per poter inviare la notifica al responsabile (se necessario)
        $this->Group_mdl->getById(Group_mdl::getCurrentGroupAssignmentId($this->User_mdl->id));
        $sendNotification = ($this->Group_mdl->managerId != $userId ? TRUE : FALSE);

        // se la richiesta proviene dalla webapp, verifichiamo l'indirizzo IP di provenienza
        if ($isWebAppRequest && !$this->config->item('badgeFromOutsideKnownNetworks') && !Office_ip_mdl::getOfficeByIp($this->input->ip_address())) {
            invalidRequest();
        }

        // l'ultimo record di tipo 'badge' per l'utente è aperto?
        $lastOpenAccessRecordId = Badge_mdl::getLastOpenedAccessRecord($userId);

        // se non è stata indicata l'azione da eseguire (badgeIn / bagdeOut) la individuiamo in base alla situazione attuale per l'utente
        if ($this->input->post('action', TRUE) && in_array($this->input->post('action', TRUE), array('badgeIn', 'badgeOut'))) {
            $action = $this->input->post('action', TRUE);
        } else {
            $action = ($lastOpenAccessRecordId ? 'badgeOut' : 'badgeIn');
        }

        /*
         * Gestiamo i 4 stati della richiesta
         * 1. badgeIn e non ci sono badge aperti
         * 2. badgeIn e ci sono badge aperti
         * 3. badgeOut e non ci sono badge aperti
         * 4. badgeOut e ci sono badge aperti
         */
        if ($action == 'badgeIn' && !$lastOpenAccessRecordId) {
            /*
             * badgeIn e non ci sono badge aperti
             * - registra l'ingresso
             */
            $this->Badge_mdl->userId = $userId;
            $this->Badge_mdl->execAccess();
        } elseif ($action == 'badgeIn' && $lastOpenAccessRecordId) {
            /*
             * badgeIn e ci sono badge aperti
             * - non fare nulla!
             */
        } elseif ($action == 'badgeOut' && !$lastOpenAccessRecordId) {
            /*
             * badgeOut e non ci sono badge aperti
             * - non fare nulla!
             */
        } elseif ($action == 'badgeOut' && $lastOpenAccessRecordId && $this->Badge_mdl->getById($lastOpenAccessRecordId)) {
            /*
             * badgeOut e ci sono badge aperti
             * - registra l'uscita
             */
            $this->Badge_mdl->execExit();
        } else {
            // ?????? - richiesta non valida
            invalidRequest();
        }

        // invia l'eventuale notifica
        if ($sendNotification) {
            $label = ($action == 'badgeIn' ? 'labelBadgeTypeAccess' : 'labelBadgeTypeExit');
            $realName = $this->User_mdl->surname . ' ' . $this->User_mdl->name;
            Notifications::addToQueue($this->Group_mdl->managerId, $label, $realName);
        }

        // setta il flag di stato e il messaggio di conferma, dopo l'esecuzione della richiesta 
        if ($action == 'badgeIn') {
            $this->response['confiormationMsg'] = lang('accessTime') . ': ' . mdate('%H:%i:%s') . ' (' . lang(strtolower(mdate('%l'))) . ' ' . mdate('%d/%m/%Y') . ')';
            $this->response['currentStatus'] = 1;
        } else {
            $this->response['confiormationMsg'] = lang('exitTime') . ': ' . mdate('%H:%i:%s') . ' (' . lang(strtolower(mdate('%l'))) . ' ' . mdate('%d/%m/%Y') . ')';
            $this->response['currentStatus'] = 0;
        }

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));










        /*
         * 
         * 
         * 
         * 
         * 
         * 
         * 
         * 
         */

        /*


          // only ajax requests are accepted
          if (!$this->input->is_ajax_request()) {
          invalidRequest();
          }

          // init the response container
          $this->response = array();
          $this->response['validation'] = TRUE;

          // check for a valid target user id
          if (!$this->User_mdl->getById($this->session->userdata('userId'))) {
          invalidRequest();
          }

          // get associated user group
          $this->Group_mdl->getById(Group_mdl::getCurrentGroupAssignmentId($this->User_mdl->id));
          $sendNotification = FALSE;
          if ($this->Group_mdl->managerId != $this->session->userdata('userId')) {
          $sendNotification = TRUE;
          }

          // verify if badge is enabled from outside known networks
          if (!$this->config->item('badgeFromOutsideKnownNetworks') && !$this->session->userdata('knownAccessLocation')) {
          invalidRequest();
          }

          // check for open record associated to the user
          $lastOpenAccessRecordId = Badge_mdl::getLastOpenedAccessRecord($this->session->userdata('userId'));

          if ($lastOpenAccessRecordId) {
          // last record is open - register user exit
          $this->Badge_mdl->getById($lastOpenAccessRecordId);
          $this->Badge_mdl->exitDateTime = mdate('%Y-%m-%d %H:%i:%s');

          $this->response['confiormationMsg'] = lang('exitTime') . ': ' . mdate('%H:%i:%s') . ' (' . lang(strtolower(mdate('%l'))) . ' ' . mdate('%d/%m/%Y') . ')';
          $this->response['targetId'] = $this->Badge_mdl->execExit();
          $this->response['currentStatus'] = 0;

          // send notification
          if ($sendNotification) {
          Notifications::addToQueue($this->Group_mdl->managerId, 'labelBadgeTypeExit', $this->session->userdata('realName'));
          }

          // clean up records (when needed)
          Badge_mdl::cleanUpDuplicatedBadges($this->session->userdata('userId'), $this->Badge_mdl->accessDateTime);
          } else {

         */



        /*
         * no open record found - register user access
         * 
         * extract operative engage time shifts defined from the day before 
         * to the day after (to handle late / early accesses), and match the 
         * time shift to associate the record 
         * 
         */


        /*

          $this->Badge_mdl->userId = $this->session->userdata('userId');
          $this->Badge_mdl->accessDateTime = mdate('%Y-%m-%d %H:%i:%s');
          $this->Badge_mdl->absenceType = 0;

          $this->response['confiormationMsg'] = lang('accessTime') . ': ' . mdate('%H:%i:%s') . ' (' . lang(strtolower(mdate('%l'))) . ' ' . mdate('%d/%m/%Y') . ')';
          $this->response['targetId'] = $this->Badge_mdl->execAccess();
          $this->response['currentStatus'] = 1;

          // send notification
          if ($sendNotification) {
          Notifications::addToQueue($this->Group_mdl->managerId, 'labelBadgeTypeAccess', $this->session->userdata('realName'));
          }
          }


          $this->output
          ->set_content_type('application/json')
          ->set_output(json_encode($this->response));


         */
    }

    /**
     * Show all user accesses
     */
    function userAccesses() {

        $this->load->model('User_mdl');
        $this->load->model('Group_mdl');
        $this->load->model('Badge_mdl');

        $reqView = $this->input->post('view', TRUE);
        $targetUserId = $this->input->post('userId', TRUE);

        if (!$this->User_mdl->getById($this->session->userdata('userId'))) {
            invalidRequest();
        }

        // check for a valid target user id
        if (strlen($targetUserId) > 0 && !$this->User_mdl->getById($targetUserId)) {
            invalidRequest();
        }

        $data['getConfigAction'] = 'Calendar_ctrl/getConfigParams';
        $data['defaultView'] = (in_array($reqView, array('month', 'basicWeek', 'basicDay', 'agendaWeek', 'agendaDay')) ? $reqView : 'month');
        $data['baseAction'] = 'Badge_ctrl/userAccesses';
        $data['getBadgesHistory'] = 'Calendar_ctrl/getCalendarData/handleBadgeHistory/' . $targetUserId;
        $data['addBadgeAction'] = 'Badge_ctrl/addBadge';
        $data['editBadgeAction'] = 'Badge_ctrl/editBadge';
        $data['deleteBadgeAction'] = 'Badge_ctrl/deleteBadge';

        // filter select box
        $data['userList'] = populateSelectBox(User_mdl::getUsersList(), $targetUserId, lang('selectUser'));

        $this->load->view('templates/header', $data);
        $this->load->view('badgeView/calendarUserAccessesView', $data);
        $this->load->view('templates/footer', $data);
    }

    /**
     * register new badge for onother user
     */
    function addBadge() {

        $this->load->model('Badge_mdl');
        $this->load->model('User_mdl');
        $this->load->model('Group_mdl');

        // only ajax requests are accepted
        if (!$this->input->is_ajax_request()) {
            invalidRequest();
        }

        // user must have administrative profile
        if (!$this->User_mdl->getById($this->session->userdata('userId'))) {
            invalidRequest();
        }

        // init the response container
        $this->response = array();
        $this->response['validation'] = TRUE;
        $currentDate = mdate('%Y-%m-%d %H:%i:%s');

        $this->form_validation->set_rules('userId', lang('userRealname'), 'required|exists[users.id]');
        $this->form_validation->set_rules('badgeDateBegin', lang('badgeDateBegin'), 'required|is_date');
        $this->form_validation->set_rules('badgeTimeBegin', lang('badgeTimeBegin'), 'required|is_time');
        $this->form_validation->set_rules('badgeDateEnd', lang('badgeDateEnd'), 'is_date');
        $this->form_validation->set_rules('badgeTimeEnd', lang('badgeTimeEnd'), 'is_time');

        // check for invalid attributes
        if (!$this->form_validation->run()) {
            // form errors
            $this->response['validation'] = FALSE;
            $this->response['errorDetected'] = $this->form_validation->error_array();
        }

        // get post data
        $userId = (int) $this->input->post('userId', TRUE);
        $dateBegin = $this->input->post('badgeDateBegin', TRUE);
        $timeBegin = $this->input->post('badgeTimeBegin', TRUE);
        $dateEnd = $this->input->post('badgeDateEnd', TRUE);
        $timeEnd = $this->input->post('badgeTimeEnd', TRUE);

        // check for valid exit time (if defined)
        if ((strlen($dateEnd) > 0 && strlen($timeEnd) == 0) || (strlen($dateEnd) == 0 && strlen($timeEnd) > 0)) {
            $this->response['validation'] = FALSE;
            $this->response['errorDetected']['badgeDateEnd'] = lang('errInvalidExitTime');
        }

        if ($this->response['validation']) {
            $postData['userId'] = $userId;
            $postData['accessDateTime'] = dateAndTimeToMysqlTimestamp($dateBegin, $timeBegin);
            $postData['exitDateTime'] = (strlen($dateEnd) > 0 && strlen($timeEnd) > 0 ? dateAndTimeToMysqlTimestamp($dateEnd, $timeEnd) : '0000-00-00 00:00:00');
            $this->Badge_mdl->exchangeArray($postData);
        }

        // check for a valid target id
        if (!$this->User_mdl->getById($this->Badge_mdl->userId)) {
            invalidRequest();
        }

        // new interval must not overlap other badges
        if ($this->response['validation']) {

            $badges = Badge_mdl::getUserBadges($this->Badge_mdl->accessDateTime, ( $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' ? $this->Badge_mdl->exitDateTime : $currentDate), array($this->Badge_mdl->userId), FALSE);
            $badgeIntervals = array();
            foreach ($badges as $badge) {
                if ($badge['id'] != $this->Badge_mdl->id) {
                    $badgeIntervals[] = array(
                        0 => $badge['start'],
                        1 => ( $badge['end'] != '0000-00-00 00:00:00' ? $badge['end'] : $currentDate )
                    );
                }
            }

            if (count(getOverlappedIntervals($this->Badge_mdl->accessDateTime, ( $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' ? $this->Badge_mdl->exitDateTime : $currentDate), $badgeIntervals)) > 0) {
                $this->response['validation'] = FALSE;
                $this->response['errorDetected'][''] = lang('errBadgeOverlapping');
            }
        }

        /*
         * badge can be inserted with ending datetime major than current datetime!!!!
         */

        // new ending date time (if defined) must be major than starting time
        if ($this->response['validation'] && $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' && mysqlTimestampToUnixTimestamp($this->Badge_mdl->accessDateTime) > mysqlTimestampToUnixTimestamp($this->Badge_mdl->exitDateTime)) {
            $this->response['validation'] = FALSE;
            $this->response['errorDetected'][''] = lang('errInvalidExitTime');
        }

        // start datetime could not be major than current datetime
        if ($this->Badge_mdl->accessDateTime > $currentDate) {
            $this->response['validation'] = FALSE;
            $this->response['errorDetected'][''] = lang('errInvalidAccessTime');
        }

        if ($this->response['validation']) {
            // perform the operation
            $this->Badge_mdl->execAdd();
            $this->response['targetId'] = $this->Badge_mdl->id;

            // extract user manager
            $managerContactEmail = '';
            $managerData = Group_mdl::getHandlerUserRequests($this->User_mdl->id);
            if (array_key_exists('managerId', $managerData) && $this->User_mdl->id != $managerData['managerId']) {
                $manager = new User_mdl();
                $manager->getById($managerData['managerId']);
                $managerContactEmail = $manager->contactEmail;
            }

            // send email to the user
            $userRealName = $this->User_mdl->surname . ' ' . $this->User_mdl->name;
            $this->Badge_mdl->sendMailConfirmationAddBadge($this->User_mdl->contactEmail, $managerContactEmail, $userRealName, $this->Badge_mdl->accessDateTime, $this->Badge_mdl->exitDateTime);
        }

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));
    }

    /**
     * edit badge start / end time
     */
    function editBadge() {

        $this->load->model('Badge_mdl');
        $this->load->model('User_mdl');
        $this->load->model('Group_mdl');
        $this->load->model('Project_activity_mdl');
        $this->load->model('Report_presences_mdl');

        // only ajax requests are accepted
        if (!$this->input->is_ajax_request()) {
            invalidRequest();
        }

        // check for a valid target id
        if (!$this->Badge_mdl->getById((int) $this->input->post('id', TRUE))) {
            invalidRequest();
        }

        // check for a valid target user id
        if (!$this->User_mdl->getById($this->Badge_mdl->userId)) {
            invalidRequest();
        }

        // extract current end time
        $currentDateTimeBegin = $this->Badge_mdl->accessDateTime;
        $currentDateTimeEnd = $this->Badge_mdl->exitDateTime;

        // init the response container
        $this->response = array();
        $this->response['validation'] = TRUE;

        $this->form_validation->set_rules('badgeDateBegin', lang('badgeDateBegin'), 'required|is_date');
        $this->form_validation->set_rules('badgeTimeBegin', lang('badgeTimeBegin'), 'required|is_time');
        $this->form_validation->set_rules('badgeDateEnd', lang('badgeDateEnd'), 'is_date');
        $this->form_validation->set_rules('badgeTimeEnd', lang('badgeTimeEnd'), 'is_time');

        // check for invalid attributes
        if (!$this->form_validation->run()) {
            // form errors
            $this->response['validation'] = FALSE;
            $this->response['errorDetected'] = $this->form_validation->error_array();
        }

        // get post data
        $dateBegin = $this->input->post('badgeDateBegin', TRUE);
        $timeBegin = $this->input->post('badgeTimeBegin', TRUE);
        $dateEnd = $this->input->post('badgeDateEnd', TRUE);
        $timeEnd = $this->input->post('badgeTimeEnd', TRUE);

        // exit date time must be setted for already closed badges
        if ($this->response['validation'] && $currentDateTimeEnd != '0000-00-00 00:00:00' && ( strlen($dateEnd) == 0 || strlen($timeEnd) == 0 )) {
            $this->response['validation'] = FALSE;
            $this->response['errorDetected']['id'] = lang('errSetExitTimeClosedEvent');
        }

        if ($this->response['validation']) {
            $postData['id'] = $this->Badge_mdl->id;
            $postData['userId'] = $this->Badge_mdl->userId;
            $postData['accessDateTime'] = dateAndTimeToMysqlTimestamp($dateBegin, $timeBegin);
            $postData['exitDateTime'] = (strlen($dateEnd) > 0 && strlen($timeEnd) > 0 ? dateAndTimeToMysqlTimestamp($dateEnd, $timeEnd) : '0000-00-00 00:00:00');
            $this->Badge_mdl->exchangeArray($postData);
        }

        // exit date time (if defined) must be major than starting time
        if ($this->response['validation'] && $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' && mysqlTimestampToUnixTimestamp($this->Badge_mdl->accessDateTime, FALSE) > mysqlTimestampToUnixTimestamp($this->Badge_mdl->exitDateTime, FALSE)) {
            $this->response['validation'] = FALSE;
            $this->response['errorDetected'][''] = lang('errInvalidExitTime');
        }

        // new interval must not overlap other badges
        if ($this->response['validation']) {

            $badges = Badge_mdl::getUserBadges($this->Badge_mdl->accessDateTime, ( $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' ? $this->Badge_mdl->exitDateTime : mdate('%Y-%m-%d %H:%i:%s')), array($this->Badge_mdl->userId), FALSE);
            $badgeIntervals = array();
            foreach ($badges as $badge) {
                if ($badge['id'] != $this->Badge_mdl->id) {
                    $badgeIntervals[] = array(
                        0 => $badge['start'],
                        1 => ( $badge['end'] != '0000-00-00 00:00:00' ? $badge['end'] : mdate('%Y-%m-%d %H:%i:%s') )
                    );
                }
            }

            if (count(getOverlappedIntervals($this->Badge_mdl->accessDateTime, ( $this->Badge_mdl->exitDateTime != '0000-00-00 00:00:00' ? $this->Badge_mdl->exitDateTime : mdate('%Y-%m-%d %H:%i:%s')), $badgeIntervals)) > 0) {
                $this->response['validation'] = FALSE;
                $this->response['errorDetected'][''] = lang('errBadgeOverlapping');
            }
        }

        if ($this->response['validation']) {

            // perform the operation
            $this->Badge_mdl->execEdit();
            $this->response['targetId'] = $this->Badge_mdl->id;
        }

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));
    }

    /**
     * delete badge
     */
    function deleteBadge() {

        $this->load->model('Badge_mdl');
        $this->load->model('User_mdl');
        $this->load->model('Group_mdl');
        $this->load->model('Project_activity_mdl');
        $this->load->model('Report_presences_mdl');

        // only ajax requests are accepted
        if (!$this->input->is_ajax_request()) {
            invalidRequest();
        }

        // check for a valid target id
        if (!$this->Badge_mdl->getById((int) $this->input->post('id', TRUE))) {
            invalidRequest();
        }

        // check for a valid target user id
        if (!$this->User_mdl->getById($this->Badge_mdl->userId)) {
            invalidRequest();
        }

        // init the response container
        $this->response = array();
        $this->response['validation'] = TRUE;

        // perform the operation
        $this->Badge_mdl->execDelete();
        $this->response['targetId'] = $this->Badge_mdl->id;

        $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($this->response));
    }

}
