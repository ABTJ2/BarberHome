<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Services extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_manager(); $this->load->model('Service_model'); }
    public function index(){
        $q=trim((string)$this->input->get('q',TRUE));$archived=$this->input->get('archived')==='1';
        $this->render('services/index',array('services'=>$this->Service_model->all(FALSE,$archived,$q),'q'=>$q,'archived'=>$archived),'Servicios');
    }
    public function create(){ $this->render('services/form',array('service'=>NULL,'errors'=>array()),'Nuevo servicio'); }
    private function validate(){ $e=array(); if(trim($this->posted('name',''))==='')$e[]='El nombre es obligatorio.'; if(!valid_money($this->posted('price')))$e[]='El precio debe ser válido y no negativo.'; $duration=$this->posted('duration_minutes');if(!is_scalar($duration)||!preg_match('/^[1-9][0-9]{0,3}$/D',(string)$duration))$e[]='La duración debe ser mayor a 0.'; return $e; }
    private function data(){ return array('name'=>trim($this->posted('name','')),'price'=>(float)$this->posted('price',0),'duration_minutes'=>(int)$this->posted('duration_minutes',0),'active'=>$this->posted('active',0)?1:0,'updated_at'=>date('Y-m-d H:i:s')); }
    public function store(){ $this->require_post();$e=$this->validate(); if($e){$this->render('services/form',array('service'=>$this->input->post(NULL,TRUE),'errors'=>$e),'Nuevo servicio');return;} $d=$this->data();$d['created_at']=date('Y-m-d H:i:s');$this->Service_model->create($d);$this->session->set_flashdata('success','Servicio creado.');redirect('servicios'); }
    public function edit($id){$s=$this->Service_model->find($id);if(!$s)show_404();$this->render('services/form',array('service'=>$s,'errors'=>array()),'Editar servicio');}
    public function update($id){$this->require_post();$s=$this->Service_model->find($id);if(!$s)show_404();$e=$this->validate();if($e){$this->render('services/form',array('service'=>array_merge($s,$this->input->post(NULL,TRUE)),'errors'=>$e),'Editar servicio');return;}$this->Service_model->update_service($id,$this->data());$this->session->set_flashdata('success','Servicio actualizado.');redirect('servicios');}
    public function delete($id){
        $this->require_post();if(!$this->Service_model->find($id))show_404();
        $future=$this->db->from('appointment_services x')->join('appointments a','a.id=x.appointment_id')
            ->where('x.service_id',(int)$id)->where('a.status','reserved')
            ->where('a.start_at >=',date('Y-m-d H:i:s'))->count_all_results();
        if($future){
            $this->session->set_flashdata('error','El servicio está en turnos futuros. Modificalos o cancelalos antes de eliminarlo.');
        }else{
            $this->Service_model->archive($id);
            $this->session->set_flashdata('success','Servicio eliminado de la lista. Los precios históricos se conservaron.');
        }
        redirect('servicios');
    }
    public function restore($id){
        $this->require_post();
        if(!$this->db->where('id',(int)$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->count_all_results('services'))show_404();
        $this->Service_model->restore($id);
        $this->session->set_flashdata('success','Servicio restaurado.');
        redirect('servicios?archived=1');
    }
}
