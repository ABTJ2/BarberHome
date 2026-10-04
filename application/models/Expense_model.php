<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Expense_model extends CI_Model {
    public function categories(){ return $this->db->where('active',1)->order_by('name')->get('expense_categories')->result_array(); }
    public function valid_category($id){return $id>0 && $this->db->where('id',$id)->where('active',1)->count_all_results('expense_categories')>0;}
    public function all_between($from,$to,$q='',$archived=FALSE){
        $this->db->select('e.*,ec.name category_name,u.full_name created_by_name')
            ->from('expenses e')->join('expense_categories ec','ec.id=e.category_id')
            ->join('users u','u.id=e.created_by')
            ->where($archived ? 'e.voided_at IS NOT NULL' : 'e.voided_at IS NULL',NULL,FALSE)
            ->where('e.expense_date >=',$from)->where('e.expense_date <=',$to);
        if($q!=='')$this->db->group_start()->like('e.concept',$q)->or_like('ec.name',$q)->group_end();
        return $this->db->order_by('e.expense_date','DESC')->order_by('e.id','DESC')->get()->result_array();
    }
    public function find($id){ return $this->db->where('id',$id)->get('expenses')->row_array(); }
    public function create($data){ $this->db->insert('expenses',$data); return $this->db->insert_id(); }
    public function update_expense($id,$data){ return $this->db->where('id',$id)->where('voided_at IS NULL',NULL,FALSE)->update('expenses',$data); }
    public function void_expense($id,$user_id){return $this->db->where('id',$id)->where('voided_at IS NULL',NULL,FALSE)->update('expenses',array('voided_at'=>date('Y-m-d H:i:s'),'voided_by'=>$user_id));}
    public function restore_expense($id){return $this->db->where('id',$id)->where('voided_at IS NOT NULL',NULL,FALSE)->update('expenses',array('voided_at'=>NULL,'voided_by'=>NULL));}
    public function total_between($from,$to){ $r=$this->db->select_sum('amount','total')->where('voided_at IS NULL',NULL,FALSE)->where('expense_date >=',$from)->where('expense_date <=',$to)->get('expenses')->row_array(); return (float)($r['total']?:0); }
}
