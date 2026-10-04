<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Service_model extends CI_Model {
    public function all($active_only=FALSE,$archived=FALSE,$q=''){
        $this->db->from('services')->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL',NULL,FALSE);
        if($active_only)$this->db->where('active',1);
        if($q!=='')$this->db->like('name',$q);
        return $this->db->order_by('name')->get()->result_array();
    }
    public function find($id){ return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->get('services')->row_array(); }
    public function get_many($ids){ if(!$ids)return array(); return $this->db->where_in('id',array_map('intval',$ids))->where('active',1)->where('deleted_at IS NULL',NULL,FALSE)->get('services')->result_array(); }
    public function archive($id){return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->update('services',array('active'=>0,'deleted_at'=>date('Y-m-d H:i:s')));}
    public function restore($id){return $this->db->where('id',$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->update('services',array('active'=>1,'deleted_at'=>NULL));}
    public function create($data){ $this->db->insert('services',$data); return $this->db->insert_id(); }
    public function update_service($id,$data){ return $this->db->where('id',$id)->update('services',$data); }
}
