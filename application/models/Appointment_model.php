<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Appointment_model extends CI_Model {
    public $last_error='No se pudo guardar el turno.';

    public function for_date($date,$barber_id=NULL,$q='',$status=''){
        $this->db->select("a.*, CONCAT(c.first_name,' ',c.last_name) client_name, c.phone client_phone,
            b.full_name barber_name,
            (SELECT COALESCE(SUM(x.price_snapshot),0) FROM appointment_services x WHERE x.appointment_id=a.id) estimated_total,
            (SELECT COALESCE(SUM(x.duration_snapshot),0) FROM appointment_services x WHERE x.appointment_id=a.id) service_minutes,
            (SELECT COUNT(*) FROM payments p WHERE p.appointment_id=a.id) has_payment,
            (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM appointment_services x
             JOIN services s ON s.id=x.service_id WHERE x.appointment_id=a.id) services",FALSE);
        $this->db->from('appointments a');
        $this->db->join('clients c','c.id=a.client_id');
        $this->db->join('barbers b','b.id=a.barber_id');
        if($date)$this->db->where('DATE(a.start_at)',$date);
        if($barber_id) $this->db->where('a.barber_id',$barber_id);
        if($status!=='')$this->db->where('a.status',$status);
        if($q!==''){
            $this->db->group_start()->like('c.first_name',$q)->or_like('c.last_name',$q)
                ->or_like('c.phone',$q)->or_like('b.full_name',$q)->group_end();
        }
        return $this->db->order_by('a.start_at')->get()->result_array();
    }

    public function find($id){
        $row=$this->db->select("a.*, CONCAT(c.first_name,' ',c.last_name) client_name,
            c.phone client_phone,b.full_name barber_name",FALSE)
            ->from('appointments a')->join('clients c','c.id=a.client_id')
            ->join('barbers b','b.id=a.barber_id')->where('a.id',$id)->get()->row_array();
        if(!$row) return NULL;

        $row['services']=$this->services($id);
        $row['payment']=$this->db->select('p.*,pm.name payment_method')
            ->from('payments p')->join('payment_methods pm','pm.id=p.payment_method_id')
            ->where('p.appointment_id',$id)->get()->row_array();
        return $row;
    }

    public function services($appointment_id){
        return $this->db->select('x.*,s.name')->from('appointment_services x')
            ->join('services s','s.id=x.service_id')
            ->where('x.appointment_id',$appointment_id)->order_by('s.name')->get()->result_array();
    }

    public function overlap_exists($barber_id,$start,$end,$exclude_id=NULL){
        // Dos intervalos se cruzan si el comienzo de uno es anterior al fin del otro.
        $this->db->from('appointments')->where('barber_id',$barber_id);
        $this->db->where_in('status',array('reserved','attended'));
        $this->db->where('start_at <',$end)->where('end_at >',$start);
        if($exclude_id) $this->db->where('id !=',$exclude_id);
        return $this->db->count_all_results()>0;
    }

    public function slot_validation($barber_id,$service_ids,$date,$time,$exclude_id=NULL){
        $this->load->model('Service_model');
        $this->load->model('Barber_model');

        if(!is_array($service_ids) || !$service_ids) return array(FALSE,'Seleccioná servicios válidos.',NULL,NULL,array());
        $ids=array();
        foreach($service_ids as $id){
            if(!ctype_digit((string)$id) || (int)$id<1 || in_array((int)$id,$ids,TRUE))
                return array(FALSE,'Seleccioná servicios válidos sin repetir.',NULL,NULL,array());
            $ids[]=(int)$id;
        }

        $services=$this->Service_model->get_many($ids);
        if(count($services)!==count($ids))
            return array(FALSE,'Seleccioná servicios activos y válidos.',NULL,NULL,$services);

        // En una edición se mantienen los precios y duraciones originales.
        $previous=array();
        if($exclude_id){
            foreach($this->services($exclude_id) as $old) $previous[$old['service_id']]=$old;
        }
        $minutes=0;
        foreach($services as &$service){
            if(isset($previous[$service['id']])){
                $service['price']=$previous[$service['id']]['price_snapshot'];
                $service['duration_minutes']=$previous[$service['id']]['duration_snapshot'];
            }
            $minutes+=(int)$service['duration_minutes'];
        }
        unset($service);
        if($minutes<=0) return array(FALSE,'La duración del turno no es válida.',NULL,NULL,$services);
        if(!valid_day($date) || !valid_clock($time))
            return array(FALSE,'La fecha u hora no es válida.',NULL,NULL,$services);

        $start=$date.' '.$time.':00';
        $end=date('Y-m-d H:i:s',strtotime($start.' +'.$minutes.' minutes'));
        if(substr($start,0,10)!==substr($end,0,10))
            return array(FALSE,'El turno debe terminar el mismo día.',NULL,NULL,$services);

        $barber=$this->Barber_model->find($barber_id);
        if(!$barber || !$barber['active'])
            return array(FALSE,'El peluquero no está activo.',NULL,NULL,$services);
        if(!$this->Barber_model->supports_services($barber_id,$ids))
            return array(FALSE,'El peluquero seleccionado no realiza todos los servicios elegidos.',NULL,NULL,$services);
        if(!$this->Barber_model->within_schedule($barber_id,$start,$end))
            return array(FALSE,'El turno queda fuera de los días u horarios de trabajo del peluquero.',NULL,NULL,$services);
        if($this->overlap_exists($barber_id,$start,$end,$exclude_id))
            return array(FALSE,'El peluquero ya tiene un turno que se superpone con ese horario.',NULL,NULL,$services);

        return array(TRUE,'',$start,$end,$services);
    }

    public function save_appointment($id,$data,$services){
        $this->db->trans_begin();

        // Bloquear al peluquero serializa los guardados simultáneos de su agenda.
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE',array($data['barber_id']));
        if($id){
            $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE',array($id));
            if($this->db->where('appointment_id',$id)->count_all_results('payments')){
                $this->db->trans_rollback();
                $this->last_error='El turno ya fue cobrado y no puede modificarse.';
                return FALSE;
            }
        }
        $ids=array();
        foreach($services as $service) $ids[]=$service['id'];
        list($valid,$reason)=$this->slot_validation($data['barber_id'],$ids,
            substr($data['start_at'],0,10),substr($data['start_at'],11,5),$id);
        if(!$valid){
            $this->db->trans_rollback();
            $this->last_error=$reason;
            return FALSE;
        }

        if($id){
            $this->db->where('id',$id)->update('appointments',$data);
        }else{
            $this->db->insert('appointments',$data);
            $id=$this->db->insert_id();
        }

        $previous=array();
        foreach($this->services($id) as $old) $previous[$old['service_id']]=$old;
        $this->db->where('appointment_id',$id)->delete('appointment_services');

        // Guardar el snapshot impide que cambios futuros de tarifa alteren un turno.
        foreach($services as $service){
            $old=isset($previous[$service['id']]) ? $previous[$service['id']] : NULL;
            $this->db->insert('appointment_services',array(
                'appointment_id'=>$id,
                'service_id'=>$service['id'],
                'price_snapshot'=>$old ? $old['price_snapshot'] : $service['price'],
                'duration_snapshot'=>$old ? $old['duration_snapshot'] : $service['duration_minutes']
            ));
        }

        if($this->db->trans_status()===FALSE){
            $this->db->trans_rollback();
            return FALSE;
        }
        $this->db->trans_commit();
        return $id;
    }

    public function update_status($id,$status){
        return $this->db->where('id',$id)->update('appointments',array(
            'status'=>$status,'updated_at'=>date('Y-m-d H:i:s')));
    }

    public function reassign($id,$barber_id,$start,$end){
        $this->db->trans_begin();
        $this->db->query('SELECT id FROM barbers WHERE id = ? FOR UPDATE',array($barber_id));
        $this->db->query('SELECT id FROM appointments WHERE id = ? FOR UPDATE',array($id));
        if($this->db->where('appointment_id',$id)->count_all_results('payments')){
            $this->db->trans_rollback();
            $this->last_error='Un turno cobrado no se puede reasignar.';
            return FALSE;
        }
        $ids=array();
        foreach($this->services($id) as $service) $ids[]=$service['service_id'];
        list($valid,$reason)=$this->slot_validation($barber_id,$ids,
            substr($start,0,10),substr($start,11,5),$id);
        if(!$valid){
            $this->db->trans_rollback();
            $this->last_error=$reason;
            return FALSE;
        }
        $this->db->where('id',$id)->update('appointments',array(
            'barber_id'=>$barber_id,'start_at'=>$start,'end_at'=>$end,
            'updated_at'=>date('Y-m-d H:i:s')));
        if($this->db->trans_status()===FALSE){$this->db->trans_rollback();return FALSE;}
        $this->db->trans_commit();
        return TRUE;
    }

    public function recent($limit=8){
        return $this->db->select("a.*,CONCAT(c.first_name,' ',c.last_name) client_name,b.full_name barber_name",FALSE)
            ->from('appointments a')->join('clients c','c.id=a.client_id')
            ->join('barbers b','b.id=a.barber_id')->order_by('a.start_at','DESC')
            ->limit($limit)->get()->result_array();
    }
}
