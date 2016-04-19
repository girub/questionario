<?php

/**
 * invoice data model
 *
 * @package PersonalData
 * @version 1.0
 * @copyright Schema31 s.p.a - 2015
 */
class Msv_questions_model extends CI_Model {

    public $que_id = null;
    public $que_sur_id = null;
    public $que_text = null;
    public $que_help_text = null;
    public $que_answer_type = null;
    public $que_required = null;
    public $orderBy = null;

    public function __construct() {
        parent::__construct();
    }

    /**
     * extract full record list
     * @param int $limit
     * @param int $offset
     * @return array of objects
     */
    public function getQuestions($que_sur_id) {

        $result = new stdClass();
        $result->validation = true;
        $this->db->select('que_text, que_help_text');
        $this->db->from('msv_questions');
        $this->db->where('que_sur_id', $que_sur_id);
        $query = $this->db->get();
        $res = $query->result();
        $result->data = $res;
        $result->httpResponse = 200;
        return $result;
    }

    public function getStatsAll($idUser, $dateFrom, $dateTo, $idInvoiceSupplier = null, $idInvoiceRecipient = null, $orderBy = null, $orderType = null, $limit = null, $offset = null) {

        $this->db->select_sum('invoicePaymentDetails.amount');

        $this->db->from('invoices');
        if ($idInvoiceSupplier != '') {
            $this->db->join('invoiceSuppliers', "invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier");
        }
        if ($idInvoiceRecipient != '') {
            $this->db->join('invoiceRecipients', "invoices.idInvoiceRecipient = invoiceRecipients.idInvoiceRecipient and invoiceRecipients.idUser = {$idUser}");
        }
        $this->db->join('invoiceStorageConservation', "invoices.idInvoiceStorageConservation = invoiceStorageConservation.idInvoiceStorageConservation and invoiceStorageConservation.idUser = {$idUser}");
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');

        $this->db->where('invoices.idUser', $idUser);
        if ($idInvoiceSupplier != '') {
            $this->db->where('invoices.idInvoiceSupplier', $idInvoiceSupplier);
        }
        if ($idInvoiceRecipient != '') {
            $this->db->where('invoices.idInvoiceRecipient', $idInvoiceRecipient);
        }

        $this->db->where("(invoicePaymentDetails.expirationDate >= '{$dateFrom}' and invoicePaymentDetails.expirationDate <= '{$dateTo}')", NULL, FALSE);
        $this->db->or_where("(invoicePaymentDetails.paymentDate >= '{$dateFrom}' and invoicePaymentDetails.paymentDate <= '{$dateTo}')", NULL, FALSE);

        $this->db->where("invoices.flagDeleted", 0);

        if ($orderBy != '') {
            if ($orderType == '') {
                $orderType = 'ASC';
            }
            $this->db->order_by($orderBy, $orderType);
        }

        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }

        $query = $this->db->get();
        // echo $this->db->last_query();die;
        $res = $query->result();

        return $res;
    }

    public function getListMonthInvoice($idUser, $limit = null, $offset = null) {

        $retArr = array();
        $this->db->distinct();
        $this->db->select("DATE_FORMAT(invoices.issueDate, '%m/%Y') as months");
        $this->db->from('invoices');
        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->order_by('invoices.issueDate', 'DESC');
        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get();
        $res = $query->result();
        $invoice = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $invoice[$row->months] = $row->months;
            }
        }
        $this->db->distinct();
        $this->db->select("DATE_FORMAT(invoicePaymentDetails.expirationDate, '%m/%Y') as months");
        $this->db->from('invoicePaymentDetails');
        $this->db->join('invoices', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');
        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where("invoicePaymentDetails.expirationDate IS NOT NULL", null, false);
        $this->db->order_by('invoicePaymentDetails.expirationDate', 'DESC');
        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get();
        $res = $query->result();
        $expiration = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $expiration[$row->months] = $row->months;
            }
        }
        $this->db->distinct();
        $this->db->select("DATE_FORMAT(invoicePaymentDetails.paymentScheduleDate, '%m/%Y') as months");
        $this->db->from('invoicePaymentDetails');
        $this->db->join('invoices', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');
        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where("invoicePaymentDetails.paymentScheduleDate IS NOT NULL", null, false);
        $this->db->order_by('invoicePaymentDetails.paymentScheduleDate', 'DESC');

        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get();
        $res = $query->result();
        $schedule = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $schedule[$row->months] = $row->months;
            }
        }
        $this->db->distinct();
        $this->db->select("DATE_FORMAT(invoicePaymentDetails.paymentDate, '%m/%Y') as months");
        $this->db->from('invoicePaymentDetails');
        $this->db->join('invoices', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');
        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where("invoicePaymentDetails.paymentDate IS NOT NULL", null, false);
        $this->db->order_by('invoicePaymentDetails.paymentDate', 'DESC');
        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get();
        $res = $query->result();
        $payment = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $payment[$row->months] = $row->months;
            }
        }
        $retArr = $invoice + $expiration + $schedule + $payment;
        return $retArr;
    }

    public function getListInvoiceDashboard($idUser, $dateFrom = null, $dateTo = null) {

        $oggi = date('Y-m-d');
        $arrRet = array();

        //documenti scaduti
        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate as issueDate, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, '
                . 'invoices.amount as importo, '
                . 'invoicePaymentDetails.flagPayed as flagPayed,'
                . 'invoicePaymentDetails.paymentScheduleDate as dataPagamentoProgrammato, '
                . 'invoicePaymentDetails.idInvoicePaymentDetail as dettaglioPagamentoId,  '
                . 'invoicePaymentDetails.expirationDate as dataScadenza ');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');

        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where('invoicePaymentDetails.expirationDate <', $oggi);
        $this->db->where("invoicePaymentDetails.flagPayed", 0);
        $this->db->where('invoicePaymentDetails.paymentScheduleDate is NULL', null, false);

        $this->db->order_by('invoicePaymentDetails.expirationDate', 'ASC');

        $query = $this->db->get();

        $res = $query->result();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $rec = new stdClass();
                $rec->idInvoice = $row->idInvoice;
                $rec->numero = $row->numero;
                $rec->issueDate = $row->issueDate;
                $rec->fornitore = $row->fornitore;
                $rec->causale = $row->causale;
                $rec->importo = $row->importo;
                $rec->flagPayed = $row->flagPayed;
                $rec->dataPagamentoProgrammato = $row->dataPagamentoProgrammato;
                $rec->dettaglioPagamentoId = $row->dettaglioPagamentoId;
                $rec->dataScadenza = $row->dataScadenza;
                $arrRet[] = $rec;
            }
        }

        // pendenti
        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, '
                . 'invoices.amount as importo, '
                . 'invoicePaymentDetails.flagPayed as flagPayed,'
                . 'invoicePaymentDetails.paymentScheduleDate as dataPagamentoProgrammato, '
                . 'invoicePaymentDetails.idInvoicePaymentDetail as dettaglioPagamentoId,  '
                . 'invoicePaymentDetails.expirationDate as dataScadenza ');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');

        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where('invoicePaymentDetails.expirationDate >=', $dateFrom);
        $this->db->where('invoicePaymentDetails.expirationDate <=', $dateTo);
        $this->db->where("invoicePaymentDetails.flagPayed", 0);
        $this->db->where('invoicePaymentDetails.paymentScheduleDate is NULL', null, false);
        $this->db->order_by('invoicePaymentDetails.expirationDate', 'ASC');

        $query = $this->db->get();

        $res = $query->result();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $rec = new stdClass();
                $rec->idInvoice = $row->idInvoice;
                $rec->numero = $row->numero;
                $rec->issueDate = $row->issueDate;
                $rec->fornitore = $row->fornitore;
                $rec->causale = $row->causale;
                $rec->importo = $row->importo;
                $rec->flagPayed = $row->flagPayed;
                $rec->dataPagamentoProgrammato = $row->dataPagamentoProgrammato;
                $rec->dettaglioPagamentoId = $row->dettaglioPagamentoId;
                $rec->dataScadenza = $row->dataScadenza;
                $arrRet[] = $rec;
            }
        }

        // schedulati
        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, '
                . 'invoices.amount as importo, '
                . 'invoicePaymentDetails.flagPayed as flagPayed,'
                . 'invoicePaymentDetails.paymentScheduleDate as dataPagamentoProgrammato, '
                . 'invoicePaymentDetails.idInvoicePaymentDetail as dettaglioPagamentoId,  '
                . 'invoicePaymentDetails.expirationDate as dataScadenza ');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');

        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where('invoicePaymentDetails.paymentScheduleDate >=', $dateFrom);
        $this->db->where('invoicePaymentDetails.paymentScheduleDate <=', $dateTo);
        $this->db->where("invoicePaymentDetails.flagPayed", 0);
        $this->db->order_by('invoicePaymentDetails.paymentScheduleDate', 'ASC');

        $query = $this->db->get();

        $res = $query->result();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $rec = new stdClass();
                $rec->idInvoice = $row->idInvoice;
                $rec->numero = $row->numero;
                $rec->issueDate = $row->issueDate;
                $rec->fornitore = $row->fornitore;
                $rec->causale = $row->causale;
                $rec->importo = $row->importo;
                $rec->flagPayed = $row->flagPayed;
                $rec->dataPagamentoProgrammato = $row->dataPagamentoProgrammato;
                $rec->dettaglioPagamentoId = $row->dettaglioPagamentoId;
                $rec->dataScadenza = $row->dataScadenza;
                $arrRet[] = $rec;
            }
        }

        // pagati
        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, '
                . 'invoices.amount as importo, '
                . 'invoicePaymentDetails.flagPayed as flagPayed,'
                . 'invoicePaymentDetails.paymentScheduleDate as dataPagamentoProgrammato, '
                . 'invoicePaymentDetails.idInvoicePaymentDetail as dettaglioPagamentoId,  '
                . 'invoicePaymentDetails.expirationDate as dataScadenza ');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');

        $this->db->where('invoices.idUser', $idUser);
        $this->db->where("invoices.flagDeleted", 0);
        $this->db->where('invoicePaymentDetails.paymentDate >=', $dateFrom);
        $this->db->where('invoicePaymentDetails.paymentDate <=', $dateTo);
        $this->db->where("invoicePaymentDetails.flagPayed", 1);
        $this->db->order_by('invoicePaymentDetails.paymentDate', 'ASC');

        $query = $this->db->get();

        $res = $query->result();
        if ($query->num_rows() > 0) {
            foreach ($query->result() as $row) {
                $rec = new stdClass();
                $rec->idInvoice = $row->idInvoice;
                $rec->numero = $row->numero;
                $rec->issueDate = $row->issueDate;
                $rec->fornitore = $row->fornitore;
                $rec->causale = $row->causale;
                $rec->importo = $row->importo;
                $rec->flagPayed = $row->flagPayed;
                $rec->dataPagamentoProgrammato = $row->dataPagamentoProgrammato;
                $rec->dettaglioPagamentoId = $row->dettaglioPagamentoId;
                $rec->dataScadenza = $row->dataScadenza;
                $arrRet[] = $rec;
            }
        }

        return $arrRet;
    }

    public function getListInvoice($idUser, $idInvoice = null, $idInvoiceSupplier = null, $idInvoiceRecipient = null, $type = null, $number = null, $issueDateFrom = null, $issueDateTo = null, $expirationDateFrom = null, $expirationDateTo = null, $orderBy = null, $orderType = null, $limit = null, $offset = null, $timestamp = null) {

        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate, '
                . 'invoices.type, '
                . 'invoiceRecipients.idInvoiceRecipient as idInvoiceRecipient, '
                . 'invoiceRecipients.description as recipient, '
                . 'invoiceRecipients.fiscalCode as fiscaleCodeRecipient, '
                . 'invoiceRecipients.vatNumber as vatNumberRecipient, '
                . 'invoiceSuppliers.idInvoiceSupplier as idInvoiceSupplier, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, '
                . 'invoices.amount as importo, '
                . 'invoiceStorageConservation.tsrTimeStamp, '
                . 'invoiceStorageConservation.tsrTSA,'
                . 'invoicePaymentDetails.flagPayed as flagPayed, '
                . 'invoicePaymentDetails.paymentDate as paymentDate, '
                . 'invoicePaymentDetails.paymentScheduleDate as dataPagamentoProgrammato, '
                . 'invoicePaymentDetails.idInvoicePaymentDetail as dettaglioPagamentoId,  '
                . 'invoicePaymentDetails.expirationDate as dataScadenza, '
                . 'invoicePaymentDetails.amount as detailAmount, '
                . 'invoicePaymentDetails.tsrTSA as paymentTsrTSA, '
                . 'invoicePaymentDetails.tsrTimeStamp as paymentTsrTimeStamp, '
                . '');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoiceRecipients', 'invoices.idInvoiceRecipient = invoiceRecipients.idInvoiceRecipient');
        $this->db->join('invoiceStorageConservation', 'invoices.idInvoiceStorageConservation = invoiceStorageConservation.idInvoiceStorageConservation');
        $this->db->join('invoicePaymentDetails', 'invoices.idInvoice = invoicePaymentDetails.idInvoice');
        if ($type != '') {
            $this->db->join('invoiceSupplierRules', 'invoiceSuppliers.idInvoiceSupplier = invoiceSupplierRules.idInvoiceSupplier');
            $this->db->join('invoiceSuppliersCategories', 'invoiceSupplierRules.idInvoiceSupplierCategory = invoiceSuppliersCategories.idInvoiceSupplierCategory');
        }
        $this->db->where('invoices.idUser', $idUser);

        if ($idInvoice != '') {
            $this->db->where('invoices.idInvoice', $idInvoice);
        }

        if ($idInvoiceSupplier != '') {
            $this->db->where('invoices.idInvoiceSupplier', $idInvoiceSupplier);
        }

        if ($idInvoiceRecipient != '') {
            $this->db->where('invoices.idInvoiceRecipient', $idInvoiceRecipient);
        }

        if ($type != '') {
            $this->db->where('invoiceSuppliersCategories.code', $type);
        }

        if ($number != '') {
            $this->db->where('invoices.number', $number);
        }

        if ($issueDateFrom != '') {
            $this->db->where('invoices.issueDate >=', $issueDateFrom);
        }
        if ($issueDateTo != '') {
            $this->db->where('invoices.issueDate <=', $issueDateTo);
        }

        if ($expirationDateFrom != '') {
            $this->db->where('invoicePaymentDetails.expirationDate >=', $expirationDateFrom);
        }
        if ($expirationDateTo != '') {
            $this->db->where('invoicePaymentDetails.expirationDate <=', $expirationDateTo);
        }

        $this->db->where("invoices.flagDeleted", 0);

        if ($timestamp != '') {
            $this->db->where("(invoices.updateDate > {$timestamp} or invoicePaymentDetails.updateDate > {$timestamp}", null, false);
        }
        $this->db->order_by($orderBy, $orderType);

        if ($limit != '' && $offset != '') {
            $this->db->limit($limit, $offset);
        }

        $query = $this->db->get();

        $res = $query->result();

        // echo $this->db->last_query();die();

        return $res;
    }

    public function getInvoice($idUser, $idInvoice = null, $timestamp = null) {

        $this->db->select('invoices.idInvoice, '
                . 'invoices.number as numero, '
                . 'invoices.issueDate, '
                . 'invoices.amount, '
                . 'invoices.type, '
                . 'invoiceRecipients.description as recipient, '
                . 'invoiceRecipients.fiscalCode as fiscaleCodeRecipient, '
                . 'invoiceRecipients.vatNumber as vatNumberRecipient, '
                . 'invoiceSuppliers.description as fornitore, '
                . 'invoices.causal as causale, invoices.amount as importo, '
                . 'invoiceStorageConservation.tsrTimeStamp, '
                . 'invoiceStorageConservation.fileKeyStorage, '
                . 'invoiceStorageConservation.fileVersionStorage, '
                . 'invoiceStorageConservation.tsrTSA, '
                . 'invoiceStorageConservation.tsrSerial,');

        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->join('invoiceRecipients', 'invoices.idInvoiceRecipient = invoiceRecipients.idInvoiceRecipient');
        $this->db->join('invoiceStorageConservation', 'invoices.idInvoiceStorageConservation = invoiceStorageConservation.idInvoiceStorageConservation');

        $this->db->where('invoices.idUser', $idUser);

        if ($idInvoice != '') {
            $this->db->where('invoices.idInvoice', $idInvoice);
        }

        $this->db->where("invoices.flagDeleted", 0);

        if ($timestamp != '') {
            $this->db->where("(invoices.updateDate > {$timestamp} or invoicePaymentDetails.updateDate > {$timestamp}", null, false);
        }

        $query = $this->db->get();
        if ($idInvoice != '') {
            $res = $query->row();
        } else {
            $res = $query->result();
        }

        return $res;
    }

    public function existInvoice($idUser, $datInizio, $dataFine, $numeroFattura, $pivaFornitore) {


        $this->db->select('invoices.idInvoice');
        $this->db->from('invoices');
        $this->db->join('invoiceSuppliers', 'invoices.idInvoiceSupplier = invoiceSuppliers.idInvoiceSupplier');
        $this->db->where('invoices.idUser', $idUser);
        $this->db->where('invoiceSuppliers.vatNumber', $pivaFornitore);
        $this->db->where('invoices.number', $numeroFattura);
        $this->db->where('invoices.issueDate >=', $datInizio);
        $this->db->where('invoices.issueDate <=', $dataFine);
        $this->db->where('invoices.flagDeleted', 0);

        $query = $this->db->get();


        if ($query->num_rows() > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * extract full record list
     * @param int $limit
     * @param int $offset
     * @return array of objects
     */
    public function getRecordList($idUser = null, $idInvoiceSupplier = null, $idInvoiceRecipient = null, $type = null, $number = null, $offset = NULL, $limit = NULL) {

        // extract requested records
        $this->db->from('invoices');

        if ($idUser != '') {
            $this->db->where('idUser', $idUser);
        }

        if ($idInvoiceSupplier != '') {
            $this->db->where('idInvoiceSupplier', $idInvoiceSupplier);
        }

        if ($idInvoiceRecipient != '') {
            $this->db->where('idInvoiceRecipient', $idInvoiceRecipient);
        }

        if ($type != '') {
            $this->db->where('type', $type);
        }

        $this->db->where("flagDeleted", 0);

        if ($limit != -1) {
            $this->db->limit($limit, $offset);
        }

        $query = $this->db->get();

        return $query->result();
    }

    /**
     * set object attributes
     * @param int record id
     * @return boolean TRUE if record exists, FALSE otherwise
     */
    public function getById($idInvoice) {

        $query = $this->db->get_where('invoices', array('idInvoice' => $idInvoice, 'flagDeleted' => 0));

        if ($query->num_rows()) {

            $this->idInvoice = $query->row()->idInvoice;
            $this->idUser = $query->row()->idUser;
            $this->idInvoiceSupplier = $query->row()->idInvoiceSupplier;
            $this->idInvoiceRecipient = $query->row()->idInvoiceRecipient;
            $this->idInvoiceStorageConservation = $query->row()->idInvoiceStorageConservation;
            $this->type = $query->row()->type;
            $this->specificType = $query->row()->specificType;
            $this->number = $query->row()->number;
            $this->issueDate = $query->row()->issueDate;
            $this->causal = $query->row()->causal;
            $this->amount = $query->row()->amount;
            $this->flagDeleted = $query->row()->flagDeleted;
            $this->creationDate = $query->row()->creationDate;
            $this->updateDate = $query->row()->updateDate;

            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * create a new record
     * @return int new record id
     */
    public function addRecord() {

        $this->creationDate = date("Y-m-d H:i:s");

        $this->db->set('idUser', $this->idUser);
        $this->db->set('idInvoiceSupplier', $this->idInvoiceSupplier);
        $this->db->set('idInvoiceRecipient', $this->idInvoiceRecipient);
        $this->db->set('idInvoiceStorageConservation', $this->idInvoiceStorageConservation);
        $this->db->set('type', $this->type);
        $this->db->set('specificType', $this->specificType);
        $this->db->set('number', $this->number);
        $this->db->set('issueDate', $this->issueDate);
        $this->db->set('causal', $this->causal);
        $this->db->set('amount', $this->amount);
        $this->db->set('creationDate', $this->creationDate);
        $this->db->set('flagDeleted', 0);

        $this->db->insert('invoices');

        $ret = $this->getById($this->db->insert_id());

        if ($ret) {
            return $this->idInvoice;
        } else {
            return FALSE;
        }
    }

    /**
     * edit an already defined record
     */
    public function editRecord() {

        $this->db->set('idUser', $this->idUser);
        $this->db->set('idInvoiceSupplier', $this->idInvoiceSupplier);
        $this->db->set('idInvoiceRecipient', $this->idInvoiceRecipient);
        $this->db->set('idInvoiceStorageConservation', $this->idInvoiceStorageConservation);
        $this->db->set('type', $this->type);
        $this->db->set('specificType', $this->specificType);
        $this->db->set('number', $this->number);
        $this->db->set('causal', $this->causal);
        $this->db->set('amount', $this->amount);

        $this->db->where('idInvoice', $this->idInvoice);
        $this->db->update('invoices');
        return TRUE;
    }

    /**
     * delete an already defined record (logical delete)
     */
    public function deleteRecord() {

        $this->flagDeleted = 1;
        $this->db->set('flagDeleted', $this->flagDeleted);
        $this->db->where('idInvoice', $this->idInvoice);
        $this->db->update('invoices');
        return TRUE;
    }

}
