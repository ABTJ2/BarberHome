<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Payment_model extends CI_Model {
    public function methods($active_only=TRUE){
        if($active_only) $this->db->where('active',1);
        return $this->db->order_by('name')->get('payment_methods')->result_array();
    }

    public function create_method($name){
        return $this->db->insert('payment_methods',array('name'=>$name,'active'=>1));
    }

    private function filter_list($q,$archived){
        $this->db->where($archived ? 'p.voided_at IS NOT NULL' : 'p.voided_at IS NULL',NULL,FALSE);
        if($q!==''){
            $this->db->group_start()->like('c.first_name',$q)->or_like('c.last_name',$q)
                ->or_like('b.full_name',$q)->or_like('pm.name',$q)->group_end();
        }
    }

    public function recent($limit=30,$q='',$archived=FALSE){
        $this->db->select("p.*,pm.name payment_method,
            CONCAT(c.first_name,' ',c.last_name) client_name,b.full_name barber_name",FALSE)
            ->from('payments p')->join('payment_methods pm','pm.id=p.payment_method_id')
            ->join('appointments a','a.id=p.appointment_id')
            ->join('clients c','c.id=a.client_id')->join('barbers b','b.id=a.barber_id');
        $this->filter_list($q,$archived);
        return $this->db->order_by('p.paid_at','DESC')->limit($limit)->get()->result_array();
    }

    public function find($id){
        return $this->db->select('p.*,a.client_id,a.barber_id,a.start_at,pm.name payment_method')
            ->from('payments p')->join('appointments a','a.id=p.appointment_id')
            ->join('payment_methods pm','pm.id=p.payment_method_id')
            ->where('p.id',$id)->get()->row_array();
    }

    public function for_appointment($appointment_id){
        return $this->db->where('appointment_id',$appointment_id)->get('payments')->row_array();
    }

    public function void_payment($id,$user_id){
        return $this->db->where('id',$id)->where('voided_at IS NULL',NULL,FALSE)
            ->update('payments',array('voided_at'=>date('Y-m-d H:i:s'),'voided_by'=>$user_id));
    }

    public function restore_payment($id){
        return $this->db->where('id',$id)->where('voided_at IS NOT NULL',NULL,FALSE)
            ->update('payments',array('voided_at'=>NULL,'voided_by'=>NULL));
    }

    public function create_for_appointment($appointment_id,$amount,$method_id,$notes,$user_id){
        $this->db->trans_begin();

        // Bloquear el turno evita que dos personas lo cobren a la vez.
        $appointment=$this->db->query('SELECT a.*,b.commission_percent
            FROM appointments a JOIN barbers b ON b.id=a.barber_id
            WHERE a.id=? FOR UPDATE',array($appointment_id))->row_array();

        if(!$appointment || in_array($appointment['status'],array('cancelled','no_show'),TRUE)
            || $this->for_appointment($appointment_id)){
            $this->db->trans_rollback();
            return array(FALSE,'El turno no se puede cobrar en su estado actual o ya tiene un cobro.');
        }
        if(!$this->active_method($method_id)){
            $this->db->trans_rollback();
            return array(FALSE,'Seleccioná una forma de pago activa.');
        }

        // Congelar el porcentaje al cobrar protege las liquidaciones antiguas.
        $percent=(float)$appointment['commission_percent'];
        $commission=round((float)$amount*$percent/100,2);
        $this->db->insert('payments',array(
            'appointment_id'=>$appointment_id,
            'payment_method_id'=>$method_id,
            'amount'=>$amount,
            'commission_percent_snapshot'=>$percent,
            'commission_amount'=>$commission,
            'notes'=>$notes,
            'paid_at'=>date('Y-m-d H:i:s'),
            'created_by'=>$user_id
        ));
        $this->db->where('id',$appointment_id)->update('appointments',array(
            'status'=>'attended','updated_at'=>date('Y-m-d H:i:s')));

        if($this->db->trans_status()===FALSE){
            $this->db->trans_rollback();
            return array(FALSE,'No se pudo registrar el cobro.');
        }
        $this->db->trans_commit();
        return array(TRUE,'Cobro registrado e ingreso generado.');
    }

    private function active_method($id){
        return $id>0 && $this->db->where('id',$id)->where('active',1)
            ->count_all_results('payment_methods')>0;
    }

    public function update_payment($id,$amount,$method_id,$notes){
        $payment=$this->find($id);
        if(!$payment || $payment['voided_at'] || !$this->active_method($method_id)) return FALSE;
        $commission=round((float)$amount*(float)$payment['commission_percent_snapshot']/100,2);
        return $this->db->where('id',$id)->update('payments',array(
            'amount'=>$amount,'payment_method_id'=>$method_id,'commission_amount'=>$commission,
            'notes'=>$notes,'updated_at'=>date('Y-m-d H:i:s')));
    }

    public function total_between($from,$to){
        $row=$this->db->select_sum('amount','total')->where('voided_at IS NULL',NULL,FALSE)
            ->where('paid_at >=',$from.' 00:00:00')->where('paid_at <=',$to.' 23:59:59')
            ->get('payments')->row_array();
        return (float)($row['total'] ?: 0);
    }

    public function commissions_between($from,$to){
        $row=$this->db->select_sum('commission_amount','total')->where('voided_at IS NULL',NULL,FALSE)
            ->where('paid_at >=',$from.' 00:00:00')->where('paid_at <=',$to.' 23:59:59')
            ->get('payments')->row_array();
        return (float)($row['total'] ?: 0);
    }

    public function liquidation_between($from,$to){
        return $this->db->select('b.id,b.full_name,COUNT(p.id) attentions,
            SUM(p.amount) production,SUM(p.commission_amount) payout')
            ->from('payments p')->join('appointments a','a.id=p.appointment_id')
            ->join('barbers b','b.id=a.barber_id')
            ->where('p.voided_at IS NULL',NULL,FALSE)
            ->where('p.paid_at >=',$from.' 00:00:00')
            ->where('p.paid_at <=',$to.' 23:59:59')
            ->group_by(array('b.id','b.full_name'))
            ->order_by('b.full_name')->get()->result_array();
    }

    public function between($from,$to,$q='',$archived=FALSE){
        $this->db->select("p.*,pm.name payment_method,
            CONCAT(c.first_name,' ',c.last_name) client_name,b.full_name barber_name",FALSE)
            ->from('payments p')->join('payment_methods pm','pm.id=p.payment_method_id')
            ->join('appointments a','a.id=p.appointment_id')
            ->join('clients c','c.id=a.client_id')->join('barbers b','b.id=a.barber_id')
            ->where('p.paid_at >=',$from.' 00:00:00')
            ->where('p.paid_at <=',$to.' 23:59:59');
        $this->filter_list($q,$archived);
        return $this->db->order_by('p.paid_at','DESC')->get()->result_array();
    }
}
