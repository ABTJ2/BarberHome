<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Clients extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_login(); $this->load->model('Client_model'); }
    public function index(){
        $q=trim((string)$this->input->get('q',TRUE));
        $archived=$this->input->get('archived')==='1';
        $this->render('clients/index',array('clients'=>$this->Client_model->search($q,$archived),'q'=>$q,'archived'=>$archived),'Clientes');
    }
    public function create(){ $this->render('clients/form',array('client'=>NULL,'errors'=>array()),'Nuevo cliente'); }
    private function validate($id=NULL){
        $errors=array();$first=trim($this->posted('first_name',''));$last=trim($this->posted('last_name',''));$phone=trim($this->posted('phone',''));
        if($first==='')$errors[]='El nombre es obligatorio.';if($last==='')$errors[]='El apellido es obligatorio.';if($phone==='')$errors[]='El teléfono es obligatorio.';
        if(!$errors && $this->Client_model->duplicate($first,$last,$phone,$id))$errors[]='Ya existe un cliente con estos datos. Buscalo para reutilizarlo.';
        return $errors;
    }
    private function data(){ return array('first_name'=>trim($this->posted('first_name','')),'last_name'=>trim($this->posted('last_name','')),'phone'=>trim($this->posted('phone','')),'notes'=>trim($this->posted('notes','')),'updated_at'=>date('Y-m-d H:i:s')); }
    public function store(){ $this->require_post();$errors=$this->validate(); if($errors){ $this->render('clients/form',array('client'=>$this->input->post(NULL,TRUE),'errors'=>$errors),'Nuevo cliente'); return; } $d=$this->data(); $d['created_at']=date('Y-m-d H:i:s'); $id=$this->Client_model->create($d); $this->session->set_flashdata('success','Cliente registrado.'); redirect('clientes/ver/'.$id); }
    public function edit($id){ $c=$this->Client_model->find($id); if(!$c)show_404(); $this->render('clients/form',array('client'=>$c,'errors'=>array()),'Editar cliente'); }
    public function update($id){ $this->require_post();$c=$this->Client_model->find($id); if(!$c)show_404(); $errors=$this->validate($id); if($errors){ $d=array_merge($c,$this->input->post(NULL,TRUE)); $this->render('clients/form',array('client'=>$d,'errors'=>$errors),'Editar cliente'); return; } $this->Client_model->update_client($id,$this->data()); $this->session->set_flashdata('success','Cliente actualizado.'); redirect('clientes/ver/'.$id); }
    public function show($id){ $c=$this->Client_model->find($id); if(!$c)show_404(); $this->render('clients/show',array('client'=>$c,'history'=>$this->Client_model->history($id)),'Cliente'); }
    public function delete($id){
        $this->require_post();
        if(!$this->Client_model->find($id))show_404();
        if($this->Client_model->has_future_reservations($id)){
            $this->session->set_flashdata('error','Este cliente tiene turnos futuros. Cancelalos antes de eliminarlo.');
        }else{
            $this->Client_model->archive($id);
            $this->session->set_flashdata('success','Cliente eliminado de la lista. Su historial se conservó.');
        }
        redirect('clientes');
    }
    public function restore($id){
        $this->require_post();
        $client=$this->db->where('id',(int)$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->get('clients')->row_array();
        if(!$client)show_404();
        if($this->Client_model->duplicate($client['first_name'],$client['last_name'],$client['phone'])){
            $this->session->set_flashdata('error','Ya existe un cliente activo con esos datos.');
        }else{
            $this->Client_model->restore($id);
            $this->session->set_flashdata('success','Cliente restaurado.');
        }
        redirect('clientes?archived=1');
    }
}
