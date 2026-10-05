<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de usuarios del sistema (solo Encargado).
 *
 * Las contraseñas se guardan siempre como hash (password_hash),
 * nunca en texto plano.
 */
class Users extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_manager();

        $this->load->model('User_model');
    }

    /**
     * Listado de usuarios con buscador y filtro de eliminados.
     */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('users/index', array(
            'users'    => $this->User_model->all($q, $archived),
            'q'        => $q,
            'archived' => $archived,
        ), 'Usuarios');
    }

    public function create()
    {
        $this->render('users/form', array(
            'user'  => NULL,
            'roles' => $this->User_model->roles(),
            'errors'=> array(),
        ), 'Nuevo usuario');
    }

    public function edit($id)
    {
        $user = $this->User_model->find($id);

        if (!$user) {
            show_404();
        }

        $this->render('users/form', array(
            'user'  => $user,
            'roles' => $this->User_model->roles(),
            'errors'=> array(),
        ), 'Editar usuario');
    }

    /**
     * Validación del formulario.
     *
     * @param mixed $id id del usuario en edición (NULL en alta)
     */
    private function validate($id = NULL)
    {
        $errors = array();

        $username = trim($this->posted('username', ''));

        if ($username === '') {
            $errors[] = 'El usuario es obligatorio.';
        } elseif ($this->User_model->username_exists($username, $id)) {
            $errors[] = 'Ese usuario ya existe.';
        }

        if (trim($this->posted('full_name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        // El perfil debe ser uno de los roles de la base.
        $role_id = (int) $this->posted('role_id');
        $valid_roles = array_map('intval', array_column($this->User_model->roles(), 'id'));

        if (!in_array($role_id, $valid_roles, TRUE)) {
            $errors[] = 'Seleccioná un perfil válido.';
        }

        // En alta la contraseña es obligatoria; en edición solo si se cambia.
        $password = (string) $this->input->post('password');

        if ((!$id || $password !== '') && strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        }

        return $errors;
    }

    /**
     * Datos del usuario que van a la tabla users.
     *
     * @param bool $include_password si es el alta (siempre hashea)
     */
    private function data($include_password = FALSE)
    {
        $data = array(
            'username'   => trim($this->posted('username', '')),
            'full_name'  => trim($this->posted('full_name', '')),
            'role_id'    => (int) $this->posted('role_id'),
            'active'     => $this->posted('active', 0) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        );

        $password = (string) $this->input->post('password');

        // En edición la contraseña solo se reemplaza si se escribió una nueva.
        if ($include_password || $password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        return $data;
    }

    /**
     * Alta de usuario.
     */
    public function store()
    {
        $this->require_post();

        $errors = $this->validate();

        if ($errors) {
            $this->render('users/form', array(
                'user'  => $this->input->post(NULL, TRUE),
                'roles' => $this->User_model->roles(),
                'errors'=> $errors,
            ), 'Nuevo usuario');
            return;
        }

        $data = $this->data(TRUE);
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->User_model->create($data);

        $this->session->set_flashdata('success', 'Usuario creado.');
        redirect('usuarios');
    }

    /**
     * Edición de usuario.
     */
    public function update($id)
    {
        $this->require_post();

        $user = $this->User_model->find($id);

        if (!$user) {
            show_404();
        }

        $errors = $this->validate($id);

        if ($errors) {
            $this->render('users/form', array(
                'user'  => array_merge($user, $this->input->post(NULL, TRUE)),
                'roles' => $this->User_model->roles(),
                'errors'=> $errors,
            ), 'Editar usuario');
            return;
        }

        $data = $this->data(FALSE);

        // Nadie puede desactivarse ni cambiarse el perfil a sí mismo:
        // de lo contrario el sistema quedaría sin Encargado con acceso.
        $is_self = $id == $this->current_user['id'];

        if ($is_self && (!$data['active'] || $data['role_id'] != $user['role_id'])) {
            $this->session->set_flashdata('error', 'No podés desactivar ni cambiar el perfil de tu propia cuenta.');
            redirect('usuarios/editar/' . $id);
            return;
        }

        $this->User_model->update_user($id, $data);

        $this->session->set_flashdata('success', 'Usuario actualizado.');
        redirect('usuarios');
    }

    /**
     * Baja lógica de usuario: además se desactiva el acceso.
     */
    public function delete($id)
    {
        $this->require_post();

        if (!$this->User_model->find($id)) {
            show_404();
        }

        if ((int) $id === (int) $this->current_user['id']) {
            $this->session->set_flashdata('error', 'No podés eliminar tu propia cuenta.');
        } else {
            $this->User_model->archive($id);
            $this->session->set_flashdata('success', 'Usuario eliminado de la lista y acceso deshabilitado.');
        }

        redirect('usuarios');
    }

    /**
     * Restaura un usuario eliminado.
     */
    public function restore($id)
    {
        $this->require_post();

        // Solo se restaura si estaba efectivamente eliminado.
        if (!$this->User_model->is_archived($id)) {
            show_404();
        }

        $this->User_model->restore($id);

        $this->session->set_flashdata('success', 'Usuario restaurado.');
        redirect('usuarios?archived=1');
    }
}
