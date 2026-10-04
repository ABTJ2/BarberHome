<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Dashboard extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_login(); $this->load->model(array('Appointment_model','Payment_model','Expense_model')); }
    public function index(){
        $today=date('Y-m-d'); $appointments=$this->Appointment_model->for_date($today);
        $data['appointments']=$appointments; $data['counts']=array('total'=>count($appointments),'attended'=>0,'reserved'=>0,'cancelled'=>0,'no_show'=>0);
        foreach($appointments as $a){ if(isset($data['counts'][$a['status']]))$data['counts'][$a['status']]++; }
        $data['recent']=$this->Appointment_model->for_date($today);
        $data['income']=$this->Payment_model->total_between($today,$today);
        if(($this->current_user['role_code']??'')==='encargado'){
            $data['expenses']=$this->Expense_model->total_between($today,$today); $data['commissions']=$this->Payment_model->commissions_between($today,$today); $data['local_result']=$data['income']-$data['expenses']-$data['commissions'];
        }
        $this->render('dashboard/index',$data,'Inicio');
    }
}
