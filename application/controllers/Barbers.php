<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Barbers extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_manager(); $this->load->model(array('Barber_model','Service_model','Appointment_model')); }
    public function index(){
        $q=trim((string)$this->input->get('q',TRUE));$archived=$this->input->get('archived')==='1';
        $this->render('barbers/index',array('barbers'=>$this->Barber_model->all(FALSE,$archived,$q),'q'=>$q,'archived'=>$archived),'Peluqueros');
    }
    public function create(){ $this->render_form(NULL,array()); }
    public function edit($id){ $b=$this->Barber_model->find($id); if(!$b)show_404(); $this->render_form($b,array()); }
    private function render_form($barber,$errors,$posted=FALSE){ if($posted){ $schedules=array(); foreach($this->schedule_rows() as $r){ if($r['enabled'])$schedules[]=array('day_of_week'=>$r['day'],'start_time'=>$r['start'].':00','end_time'=>$r['end'].':00'); } $selected=array_map('intval',$this->input->post('service_ids')?:array()); } else { $schedules=($barber && !empty($barber['id']))?$this->Barber_model->schedules($barber['id']):array(); $selected=($barber && !empty($barber['id']))?$this->Barber_model->service_ids($barber['id']):array(); } $this->render('barbers/form',array('barber'=>$barber,'errors'=>$errors,'services'=>$this->Service_model->all(TRUE),'schedules'=>$schedules,'selected_services'=>$selected),($barber && !empty($barber['id']))?'Editar peluquero':'Nuevo peluquero'); }
    private function schedule_rows(){ $rows=array(); for($d=1;$d<=7;$d++){ $rows[]=array('day'=>$d,'enabled'=>$this->posted('day_'.$d,0)?1:0,'start'=>$this->posted('start_'.$d,'09:00'),'end'=>$this->posted('end_'.$d,'18:00')); } return $rows; }
    private function validate(){ $e=array(); if(trim($this->posted('full_name',''))==='')$e[]='El nombre es obligatorio.'; if(!valid_percent($this->posted('commission_percent')))$e[]='El porcentaje debe estar entre 0 y 100.'; $ids=$this->input->post('service_ids');if(!is_array($ids)||!$ids||count($ids)!==count(array_unique(array_map('intval',$ids)))||count($this->Service_model->get_many($ids))!==count($ids))$e[]='Seleccioná servicios activos válidos sin repetir.'; foreach($this->schedule_rows() as $r){ if($r['enabled'] && (!valid_clock($r['start'])||!valid_clock($r['end'])||$r['start'] >= $r['end']))$e[]='Revisá los horarios de '.day_name($r['day']).'.'; } return $e; }
    private function data(){ return array('full_name'=>trim($this->posted('full_name','')),'phone'=>trim($this->posted('phone','')),'commission_percent'=>(float)$this->posted('commission_percent',0),'active'=>$this->posted('active',0)?1:0,'updated_at'=>date('Y-m-d H:i:s')); }
    public function store(){ $this->require_post();$e=$this->validate(); if($e){$this->render_form(array_merge($this->input->post(NULL,TRUE),array('id'=>NULL)),$e,TRUE);return;} $d=$this->data();$d['created_at']=date('Y-m-d H:i:s');$this->Barber_model->save(NULL,$d,$this->schedule_rows(),$this->input->post('service_ids'));$this->session->set_flashdata('success','Peluquero creado.');redirect('peluqueros'); }
    public function update($id){ $this->require_post();$b=$this->Barber_model->find($id);if(!$b)show_404();$e=$this->validate();if($e){$this->render_form(array_merge($b,$this->input->post(NULL,TRUE)),$e,TRUE);return;}$this->Barber_model->save($id,$this->data(),$this->schedule_rows(),$this->input->post('service_ids'));$this->session->set_flashdata('success','Peluquero actualizado.');redirect('peluqueros'); }
    public function reassign($id){
        $b=$this->Barber_model->find($id);if(!$b)show_404();$errors=array();
        if($this->input->method(TRUE)==='POST'){
            $appointment_id=(int)$this->posted('appointment_id');$new_barber=(int)$this->posted('barber_id');$date=$this->posted('date');$time=$this->posted('time');$a=$this->Appointment_model->find($appointment_id);
            if(!$a||$a['barber_id']!=$id||$a['payment'])$errors[]='El turno no corresponde al peluquero o ya fue cobrado.'; else { $service_ids=array_column($a['services'],'service_id'); list($ok,$msg,$start,$end)=$this->Appointment_model->slot_validation($new_barber,$service_ids,$date,$time,$appointment_id); if(!$ok)$errors[]=$msg; elseif(!$this->Appointment_model->reassign($appointment_id,$new_barber,$start,$end))$errors[]=$this->Appointment_model->last_error; else { $this->session->set_flashdata('success','Turno reasignado.');redirect('peluqueros/reasignar/'.$id); } }
        }
        $this->render('barbers/reassign',array('barber'=>$b,'appointments'=>$this->Barber_model->future_appointments($id),'barbers'=>$this->Barber_model->all(TRUE),'errors'=>$errors),'Reasignar turnos');
    }
    public function delete($id){
        $this->require_post();
        if(!$this->Barber_model->find($id))show_404();
        if($this->Barber_model->future_appointments($id)){
            $this->session->set_flashdata('error','Reasigná o cancelá los turnos futuros antes de eliminar al peluquero.');
        }else{
            $this->Barber_model->archive($id);
            $this->session->set_flashdata('success','Peluquero eliminado de la lista. Su historial se conservó.');
        }
        redirect('peluqueros');
    }
    public function restore($id){
        $this->require_post();
        if(!$this->db->where('id',(int)$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->count_all_results('barbers'))show_404();
        $this->Barber_model->restore($id);
        $this->session->set_flashdata('success','Peluquero restaurado. Revisá sus horarios y servicios.');
        redirect('peluqueros?archived=1');
    }
}
