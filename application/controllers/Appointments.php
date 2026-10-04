<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Appointments extends MY_Controller {
    public function __construct(){
        parent::__construct();
        $this->require_login();
        $this->load->model(array('Appointment_model','Client_model','Barber_model','Service_model'));
    }

    public function index(){
        // Fecha vacía significa buscar en toda la agenda.
        $date=$this->input->get('date',TRUE);
        if($date===NULL) $date=date('Y-m-d');
        if($date!=='' && !valid_day($date)) $date=date('Y-m-d');
        $barber_id=(int)$this->input->get('barber_id');
        $q=trim((string)$this->input->get('q',TRUE));
        $status=(string)$this->input->get('status',TRUE);
        if(!in_array($status,array('','reserved','attended','cancelled','no_show'),TRUE))$status='';

        $this->render('appointments/index',array(
            'date'=>$date,
            'q'=>$q,
            'status'=>$status,
            'barber_id'=>$barber_id,
            'appointments'=>$this->Appointment_model->for_date($date,$barber_id ?: NULL,$q,$status),
            'barbers'=>$this->Barber_model->all()
        ),'Agenda / Turnos');
    }

    public function create(){ $this->render_form(NULL,array(),FALSE); }
    public function walkin(){ $this->render_form(NULL,array(),TRUE); }

    public function edit($id){
        $appointment=$this->Appointment_model->find($id);
        if(!$appointment) show_404();
        if($appointment['payment']){
            $this->session->set_flashdata('error','Un turno cobrado no puede modificarse. El Encargado puede corregir el cobro.');
            redirect('turnos/ver/'.$id);
            return;
        }
        $this->render_form($appointment,array(),(bool)$appointment['walk_in']);
    }

    private function render_form($appointment,$errors,$walkin,$posted=FALSE){
        if($posted){
            $ids=$this->input->post('service_ids');
            $selected=is_array($ids) ? array_map('intval',$ids) : array();
        }else{
            $selected=$appointment && !empty($appointment['services'])
                ? array_map('intval',array_column($appointment['services'],'service_id')) : array();
        }

        $available=$this->Service_model->all(TRUE);
        if($appointment && !empty($appointment['services'])){
            // El estimado de edición usa los snapshots del turno, no la tarifa de hoy.
            foreach($available as &$service){
                foreach($appointment['services'] as $saved){
                    if($service['id']==$saved['service_id']){
                        $service['price']=$saved['price_snapshot'];
                        $service['duration_minutes']=$saved['duration_snapshot'];
                    }
                }
            }
            unset($service);
        }

        $this->render('appointments/form',array(
            'appointment'=>$appointment,
            'errors'=>$errors,
            'walkin'=>$walkin,
            'clients'=>$this->Client_model->search(),
            'barbers'=>$this->Barber_model->all(TRUE),
            'services'=>$available,
            'selected_services'=>$selected
        ),$appointment && !empty($appointment['id']) ? 'Editar turno' : ($walkin ? 'Atención sin turno' : 'Nuevo turno'));
    }

    private function validate_form($exclude_id){
        $errors=array();
        $client_id=(int)$this->posted('client_id');
        $barber_id=(int)$this->posted('barber_id');
        $service_ids=$this->input->post('service_ids');
        $date=$this->posted('date');
        $time=$this->posted('time');

        if(!$this->Client_model->find($client_id)) $errors[]='Seleccioná un cliente válido.';
        if(!$barber_id) $errors[]='Seleccioná un peluquero.';
        if(!is_array($service_ids) || !$service_ids) $errors[]='Seleccioná al menos un servicio.';
        if(!valid_day($date) || !valid_clock($time)) $errors[]='Indicá una fecha y hora válidas.';
        if($errors) return array($errors,NULL,NULL,array());

        // El modelo calcula duración, comprueba horario y busca cruces reales.
        list($ok,$message,$start,$end,$services)=$this->Appointment_model->slot_validation(
            $barber_id,$service_ids,$date,$time,$exclude_id
        );
        if(!$ok) $errors[]=$message;
        return array($errors,$start,$end,$services);
    }

    private function save($id,$walkin){
        $this->require_post();
        $existing=$id ? $this->Appointment_model->find($id) : NULL;
        if($id && !$existing) show_404();
        if($existing && $existing['payment']) show_error('No se puede modificar un turno cobrado.',409);

        list($errors,$start,$end,$services)=$this->validate_form($id);
        if($errors){
            $appointment=$existing ?: array_merge($this->input->post(NULL,TRUE),array('id'=>NULL));
            $this->render_form($appointment,$errors,$walkin,TRUE);
            return;
        }

        $status=$this->posted('status','reserved');
        if(!in_array($status,array('reserved','attended','cancelled','no_show'),TRUE)){
            $this->render_form($existing,array('Estado inválido.'),$walkin,TRUE);
            return;
        }

        $data=array(
            'client_id'=>(int)$this->posted('client_id'),
            'barber_id'=>(int)$this->posted('barber_id'),
            'start_at'=>$start,
            'end_at'=>$end,
            'status'=>$status,
            'notes'=>trim($this->posted('notes','')),
            'walk_in'=>$walkin ? 1 : 0,
            'updated_at'=>date('Y-m-d H:i:s')
        );
        if(!$id){
            $data['created_at']=date('Y-m-d H:i:s');
            $data['created_by']=$this->current_user['id'];
        }

        $saved_id=$this->Appointment_model->save_appointment($id,$data,$services);
        if(!$saved_id){
            $this->session->set_flashdata('error',$this->Appointment_model->last_error);
            redirect('turnos');
            return;
        }
        $this->session->set_flashdata('success',$id ? 'Turno actualizado.' : 'Turno registrado.');
        redirect('turnos/ver/'.$saved_id);
    }

    public function store(){ $this->save(NULL,(bool)$this->posted('walk_in',0)); }
    public function update($id){ $this->save($id,(bool)$this->posted('walk_in',0)); }

    public function show($id){
        $appointment=$this->Appointment_model->find($id);
        if(!$appointment) show_404();
        $this->render('appointments/show',array('appointment'=>$appointment),'Detalle del turno');
    }

    public function status($id){
        $this->require_post();
        $appointment=$this->Appointment_model->find($id);
        if(!$appointment) show_404();
        $status=$this->posted('status');
        if(!in_array($status,array('reserved','attended','cancelled','no_show'),TRUE)
            || ($appointment['payment'] && $status!=='attended')){
            $this->session->set_flashdata('error','No se puede cambiar el estado de un turno cobrado ni usar un estado inválido.');
            redirect('turnos/ver/'.$id);
            return;
        }

        // Al reactivar una reserva cancelada o ausente comprobamos el horario otra vez.
        if(in_array($status,array('reserved','attended'),TRUE)
            && in_array($appointment['status'],array('cancelled','no_show'),TRUE)){
            list($ok,$reason)=$this->Appointment_model->slot_validation(
                $appointment['barber_id'],
                array_column($appointment['services'],'service_id'),
                substr($appointment['start_at'],0,10),
                substr($appointment['start_at'],11,5),$id
            );
            if(!$ok){
                $this->session->set_flashdata('error',$reason);
                redirect('turnos/ver/'.$id);
                return;
            }
        }
        $this->Appointment_model->update_status($id,$status);
        $this->session->set_flashdata('success','Estado actualizado.');
        redirect('turnos/ver/'.$id);
    }

    public function delete($id){
        $this->require_post();
        $appointment=$this->Appointment_model->find($id);
        if(!$appointment)show_404();
        if($appointment['payment']){
            $this->session->set_flashdata('error','Un turno cobrado no se puede eliminar. Solicitá la corrección del cobro al Encargado.');
        }else{
            // Cancelar libera el horario y conserva el historial del cliente.
            $this->Appointment_model->update_status($id,'cancelled');
            $this->session->set_flashdata('success','Turno eliminado de la agenda activa (cancelado).');
        }
        redirect('turnos/ver/'.$id);
    }
}
