<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Payments extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_login(); $this->load->model(array('Payment_model','Appointment_model')); }
    public function index(){
        $q=trim((string)$this->input->get('q',TRUE));
        $from=(string)$this->input->get('from',TRUE);$to=(string)$this->input->get('to',TRUE);
        $archived=$this->input->get('archived')==='1' && $this->current_user['role_code']==='encargado';
        if($from!=='' || $to!==''){
            $from=valid_day($from) ? $from : date('Y-m-01');
            $to=valid_day($to) ? $to : date('Y-m-d');
            $payments=$this->Payment_model->between($from,$to,$q,$archived);
        }else{
            // Buscar texto recorre todo el historial; sin búsqueda se muestran los últimos 100.
            $payments=$q!=='' ? $this->Payment_model->between('1000-01-01','9999-12-31',$q,$archived)
                : $this->Payment_model->recent(100,'',$archived);
        }
        $this->render('payments/index',array('payments'=>$payments,'q'=>$q,'from'=>$from,
            'to'=>$to,'archived'=>$archived),'Cobros');
    }
    public function create($appointment_id){ $a=$this->Appointment_model->find($appointment_id);if(!$a)show_404();if(!empty($a['payment'])){redirect('cobros');return;}$this->render('payments/form',array('appointment'=>$a,'methods'=>$this->Payment_model->methods(),'errors'=>array()),'Registrar cobro'); }
    public function store(){ $this->require_post();$id=(int)$this->posted('appointment_id');$a=$this->Appointment_model->find($id);if(!$a)show_404();$amount=$this->posted('amount');$method=(int)$this->posted('payment_method_id');$e=array();if(!valid_money($amount,TRUE))$e[]='El importe debe ser positivo y tener hasta dos decimales.';if(!$method)$e[]='Seleccioná una forma de pago.';if($e){$this->render('payments/form',array('appointment'=>$a,'methods'=>$this->Payment_model->methods(),'errors'=>$e),'Registrar cobro');return;} list($ok,$msg)=$this->Payment_model->create_for_appointment($id,$amount,$method,trim($this->posted('notes','')),$this->current_user['id']);$this->session->set_flashdata($ok?'success':'error',$msg);redirect($ok?'cobros':'turnos/ver/'.$id); }
    public function edit($id){ $this->require_manager();$p=$this->Payment_model->find($id);if(!$p || $p['voided_at'])show_404();$this->render('payments/edit',array('payment'=>$p,'methods'=>$this->Payment_model->methods(),'errors'=>array()),'Corregir cobro'); }
    public function update($id){ $this->require_manager();$this->require_post();$p=$this->Payment_model->find($id);if(!$p)show_404();$amount=$this->posted('amount');$method=(int)$this->posted('payment_method_id');if(!valid_money($amount,TRUE)||!$method){$this->render('payments/edit',array('payment'=>$p,'methods'=>$this->Payment_model->methods(),'errors'=>array('Revisá el importe y la forma de pago.')),'Corregir cobro');return;}if(!$this->Payment_model->update_payment($id,$amount,$method,trim($this->posted('notes','')))){$this->session->set_flashdata('error','Forma de pago inválida.');redirect('cobros/editar/'.$id);return;}$this->session->set_flashdata('success','Cobro corregido.');redirect('contabilidad'); }
    public function methods(){ $this->require_manager();$this->render('payments/methods',array('methods'=>$this->Payment_model->methods(FALSE)),'Formas de pago'); }
    public function store_method(){ $this->require_manager();$this->require_post();$name=trim($this->posted('name',''));if($name===''||mb_strlen($name)>80)$this->session->set_flashdata('error','Ingresá un nombre de hasta 80 caracteres.');elseif($this->db->where('name',$name)->count_all_results('payment_methods'))$this->session->set_flashdata('error','Esa forma de pago ya existe.');else{$this->Payment_model->create_method($name);$this->session->set_flashdata('success','Forma de pago agregada.');}redirect('formas-pago'); }
    public function delete($id){
        $this->require_manager();$this->require_post();
        $payment=$this->Payment_model->find($id);
        if(!$payment || $payment['voided_at'])show_404();
        $this->Payment_model->void_payment($id,$this->current_user['id']);
        $this->session->set_flashdata('success','Cobro anulado. Ya no suma a ingresos ni comisiones.');
        redirect('cobros');
    }
    public function restore($id){
        $this->require_manager();$this->require_post();
        $payment=$this->Payment_model->find($id);
        if(!$payment || !$payment['voided_at'])show_404();
        $this->Payment_model->restore_payment($id);
        $this->session->set_flashdata('success','Cobro restaurado.');
        redirect('cobros?archived=1');
    }
}
