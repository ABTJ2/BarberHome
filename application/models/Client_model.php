<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Client_model extends CI_Model {
    public function search($q='',$archived=FALSE){
        $this->db->from('clients')->order_by('last_name')->order_by('first_name');
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL',NULL,FALSE);
        if($q!==''){ $this->db->group_start()->like('first_name',$q)->or_like('last_name',$q)->or_like('phone',$q)->group_end(); }
        return $this->db->get()->result_array();
    }
    public function find($id){ return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->get('clients')->row_array(); }
    public function duplicate($first,$last,$phone,$exclude_id=NULL){
        $this->db->where('first_name',$first)->where('last_name',$last)->where('phone',$phone)->where('deleted_at IS NULL',NULL,FALSE);
        if($exclude_id)$this->db->where('id !=',$exclude_id);
        return $this->db->get('clients')->row_array();
    }
    public function create($data){ $this->db->insert('clients',$data); return $this->db->insert_id(); }
    public function update_client($id,$data){ return $this->db->where('id',$id)->update('clients',$data); }
    public function archive($id){ return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->update('clients',array('deleted_at'=>date('Y-m-d H:i:s'))); }
    public function restore($id){ return $this->db->where('id',$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->update('clients',array('deleted_at'=>NULL)); }
    public function has_future_reservations($id){
        return $this->db->where('client_id',$id)->where('status','reserved')
            ->where('start_at >=',date('Y-m-d H:i:s'))->count_all_results('appointments')>0;
    }
    public function history($client_id){
        return $this->db->select("a.*, b.full_name barber_name, (SELECT GROUP_CONCAT(s2.name ORDER BY s2.name SEPARATOR ', ') FROM appointment_services aps2 JOIN services s2 ON s2.id=aps2.service_id WHERE aps2.appointment_id=a.id) services",FALSE)
            ->from('appointments a')->join('barbers b','b.id=a.barber_id')
            ->where('a.client_id',$client_id)->order_by('a.start_at','DESC')->get()->result_array();
    }
}
