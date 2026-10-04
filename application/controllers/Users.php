<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class Users extends MY_Controller {
    public function __construct(){ parent::__construct(); $this->require_manager(); $this->load->model('User_model'); }
    public function index(){
        $q=trim((string)$this->input->get('q',TRUE));$archived=$this->input->get('archived')==='1';
        $this->render('users/index',array('users'=>$this->User_model->all($q,$archived),'q'=>$q,'archived'=>$archived),'Usuarios');
    }
    public function create(){ $this->render('users/form',array('user'=>NULL,'roles'=>$this->User_model->roles(),'errors'=>array()),'Nuevo usuario'); }
    public function edit($id){ $u=$this->User_model->find($id);if(!$u)show_404();$this->render('users/form',array('user'=>$u,'roles'=>$this->User_model->roles(),'errors'=>array()),'Editar usuario'); }
    private function validate($id=NULL){ $e=array();$username=trim($this->posted('username',''));if($username==='')$e[]='El usuario es obligatorio.';elseif($this->User_model->username_exists($username,$id))$e[]='Ese usuario ya existe.';if(trim($this->posted('full_name',''))==='')$e[]='El nombre es obligatorio.';$role=(int)$this->posted('role_id');if(!in_array($role,array_map('intval',array_column($this->User_model->roles(),'id')),TRUE))$e[]='Seleccioná un perfil válido.';$pass=(string)$this->input->post('password');if((!$id||$pass!=='')&&strlen($pass)<6)$e[]='La contraseña debe tener al menos 6 caracteres.';return $e; }
    private function data($include_password=FALSE){ $d=array('username'=>trim($this->posted('username','')),'full_name'=>trim($this->posted('full_name','')),'role_id'=>(int)$this->posted('role_id'),'active'=>$this->posted('active',0)?1:0,'updated_at'=>date('Y-m-d H:i:s'));$p=(string)$this->input->post('password');if($include_password||$p!=='')$d['password_hash']=password_hash($p,PASSWORD_DEFAULT);return $d; }
    public function store(){ $this->require_post();$e=$this->validate();if($e){$this->render('users/form',array('user'=>$this->input->post(NULL,TRUE),'roles'=>$this->User_model->roles(),'errors'=>$e),'Nuevo usuario');return;}$d=$this->data(TRUE);$d['created_at']=date('Y-m-d H:i:s');$this->User_model->create($d);$this->session->set_flashdata('success','Usuario creado.');redirect('usuarios'); }
    public function update($id){ $this->require_post();$u=$this->User_model->find($id);if(!$u)show_404();$e=$this->validate($id);if($e){$this->render('users/form',array('user'=>array_merge($u,$this->input->post(NULL,TRUE)),'roles'=>$this->User_model->roles(),'errors'=>$e),'Editar usuario');return;} $d=$this->data(FALSE); if($id==$this->current_user['id'] && (!$d['active'] || $d['role_id']!=$u['role_id'])){$this->session->set_flashdata('error','No podés desactivar ni cambiar el perfil de tu propia cuenta.');redirect('usuarios/editar/'.$id);return;}$this->User_model->update_user($id,$d);$this->session->set_flashdata('success','Usuario actualizado.');redirect('usuarios'); }
    public function delete($id){
        $this->require_post();if(!$this->User_model->find($id))show_404();
        if((int)$id===$this->current_user['id']){
            $this->session->set_flashdata('error','No podés eliminar tu propia cuenta.');
        }else{
            $this->User_model->archive($id);
            $this->session->set_flashdata('success','Usuario eliminado de la lista y acceso deshabilitado.');
        }
        redirect('usuarios');
    }
    public function restore($id){
        $this->require_post();
        if(!$this->db->where('id',(int)$id)->where('deleted_at IS NOT NULL',NULL,FALSE)->count_all_results('users'))show_404();
        $this->User_model->restore($id);
        $this->session->set_flashdata('success','Usuario restaurado.');
        redirect('usuarios?archived=1');
    }
}
