<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Auth extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->load->model('User_model'); }
    public function index(){ if($this->current_user) redirect('inicio'); else redirect('login'); }
    public function login(){
        if($this->current_user) redirect('inicio');
        $data=array('error'=>'');
        if($this->input->method(TRUE)==='POST'){
            $username=trim($this->posted('username','')); $password=(string)$this->input->post('password');
            $user=$this->User_model->find_active_by_username($username);
            if($user && password_verify($password,$user['password_hash'])){
                $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata('auth_user',array('id'=>(int)$user['id'],'username'=>$user['username'],'full_name'=>$user['full_name'],'role_code'=>$user['role_code'],'role_name'=>$user['role_name']));
                $this->db->where('id',$user['id'])->update('users',array('last_login_at'=>date('Y-m-d H:i:s')));
                redirect('inicio'); return;
            }
            $data['error']='Usuario o contraseña incorrectos.';
        }
        $this->load->view('auth/login',$data);
    }
    public function logout(){ $this->require_post(); $this->session->sess_destroy(); redirect('login'); }
}
