<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Barber_model extends CI_Model {
    public function all($active_only=FALSE,$archived=FALSE,$q=''){
        $this->db->from('barbers')->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL',NULL,FALSE);
        if($active_only)$this->db->where('active',1);
        if($q!=='')$this->db->group_start()->like('full_name',$q)->or_like('phone',$q)->group_end();
        return $this->db->order_by('full_name')->get()->result_array();
    }
    public function find($id){ return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->get('barbers')->row_array(); }
    public function archive($id){return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->update('barbers',array('active'=>0,'deleted_at'=>date('Y-m-d H:i:s')));}
    public function restore($id){return $this->db->where('id',$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->update('barbers',array('active'=>1,'deleted_at'=>NULL));}
    public function schedules($barber_id){ return $this->db->where('barber_id',$barber_id)->order_by('day_of_week')->get('barber_schedules')->result_array(); }
    public function service_ids($barber_id){ return array_map('intval',array_column($this->db->select('service_id')->where('barber_id',$barber_id)->get('barber_services')->result_array(),'service_id')); }
    public function supports_services($barber_id,$service_ids){
        $service_ids=array_values(array_unique(array_map('intval',$service_ids))); if(!$service_ids)return FALSE;
        return $this->db->where('barber_id',$barber_id)->where_in('service_id',$service_ids)->count_all_results('barber_services')===count($service_ids);
    }
    public function within_schedule($barber_id,$start_at,$end_at){
        if(substr($start_at,0,10)!==substr($end_at,0,10))return FALSE;
        $day=(int)date('N',strtotime($start_at)); $st=date('H:i:s',strtotime($start_at)); $en=date('H:i:s',strtotime($end_at));
        return $this->db->where('barber_id',$barber_id)->where('day_of_week',$day)->where('active',1)->where('start_time <=',$st)->where('end_time >=',$en)->count_all_results('barber_schedules')>0;
    }
    public function save($id,$data,$schedule_rows,$service_ids){
        $this->db->trans_start();
        if($id){ $this->db->where('id',$id)->update('barbers',$data); } else { $this->db->insert('barbers',$data); $id=$this->db->insert_id(); }
        $this->db->where('barber_id',$id)->delete('barber_schedules');
        foreach($schedule_rows as $r){ if(!empty($r['enabled'])) $this->db->insert('barber_schedules',array('barber_id'=>$id,'day_of_week'=>$r['day'],'start_time'=>$r['start'],'end_time'=>$r['end'],'active'=>1)); }
        $this->db->where('barber_id',$id)->delete('barber_services');
        foreach(array_unique(array_map('intval',$service_ids)) as $sid){ $this->db->insert('barber_services',array('barber_id'=>$id,'service_id'=>$sid)); }
        $this->db->trans_complete(); return $this->db->trans_status() ? $id : FALSE;
    }
    public function future_appointments($barber_id){
        return $this->db->select('a.*, CONCAT(c.first_name," ",c.last_name) client_name')->from('appointments a')->join('clients c','c.id=a.client_id')->where('a.barber_id',$barber_id)->where('a.start_at >=',date('Y-m-d H:i:s'))->where_in('a.status',array('reserved'))->order_by('a.start_at')->get()->result_array();
    }
}
