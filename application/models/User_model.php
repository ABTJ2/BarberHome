<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class User_model extends CI_Model {
    public function find_active_by_username($username){
        return $this->db->select('u.*, r.code role_code, r.name role_name')->from('users u')->join('roles r','r.id=u.role_id')->where('u.username',$username)->where('u.active',1)->where('u.deleted_at IS NULL',NULL,FALSE)->get()->row_array();
    }
    public function all($q='',$archived=FALSE){
        $this->db->select('u.*, r.code role_code, r.name role_name')->from('users u')
            ->join('roles r','r.id=u.role_id')->where($archived ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL',NULL,FALSE);
        if($q!=='')$this->db->group_start()->like('u.username',$q)->or_like('u.full_name',$q)->group_end();
        return $this->db->order_by('u.full_name')->get()->result_array();
    }
    public function roles(){ return $this->db->order_by('id')->get('roles')->result_array(); }
    public function find($id){ return $this->db->select('u.*, r.code role_code, r.name role_name')->from('users u')->join('roles r','r.id=u.role_id')->where('u.id',$id)->where('u.deleted_at IS NULL',NULL,FALSE)->get()->row_array(); }
    public function username_exists($username,$exclude_id=NULL){ $this->db->where('username',$username); if($exclude_id)$this->db->where('id !=',$exclude_id); return $this->db->count_all_results('users')>0; }
    public function archive($id){return $this->db->where('id',$id)->where('deleted_at IS NULL',NULL,FALSE)->update('users',array('active'=>0,'deleted_at'=>date('Y-m-d H:i:s')));}
    public function restore($id){return $this->db->where('id',$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->update('users',array('active'=>1,'deleted_at'=>NULL));}
    public function create($data){ $this->db->insert('users',$data); return $this->db->insert_id(); }
    public function update_user($id,$data){ return $this->db->where('id',$id)->update('users',$data); }
}
