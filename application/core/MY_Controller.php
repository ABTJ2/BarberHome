<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class MY_Controller extends CI_Controller {
    protected $current_user = NULL;
    public function __construct(){
        parent::__construct();
        $this->current_user = $this->session->userdata('auth_user');
        if($this->current_user){
            $this->load->model('User_model');
            $user=$this->User_model->find((int)$this->current_user['id']);
            if(!$user || !$user['active']){ $this->session->unset_userdata('auth_user'); $this->current_user=NULL; }
            else { $this->current_user=array('id'=>(int)$user['id'],'username'=>$user['username'],'full_name'=>$user['full_name'],'role_code'=>$user['role_code'],'role_name'=>$user['role_name']); $this->session->set_userdata('auth_user',$this->current_user); }
        }
        $this->load->vars('current_user', $this->current_user);
    }
    protected function require_login(){ if(!$this->current_user){ $this->session->set_flashdata('error','Iniciá sesión para continuar.'); redirect('login'); exit; } }
    protected function require_manager(){ $this->require_login(); if(($this->current_user['role_code'] ?? '') !== 'encargado'){ show_error('No tenés permisos para acceder a esta sección.',403,'Acceso restringido'); exit; } }
    protected function render($view,$data=array(),$title='Barber House'){
        $data['page_title']=$title;
        $this->load->view('layout/header',$data);
        $this->load->view('layout/sidebar',$data);
        $this->load->view($view,$data);
        $this->load->view('layout/footer',$data);
    }
    protected function posted($key,$default=NULL){ $v=$this->input->post($key,TRUE); return $v===NULL?$default:$v; }
    protected function require_post(){ if($this->input->method(TRUE)!=='POST') show_error('Operación disponible solo mediante formulario.',405); }
}
