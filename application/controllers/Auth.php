<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador de acceso al sistema.
 *
 * Es el único controlador accesible sin sesión.
 */
class Auth extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->load->model('User_model');
    }

    /**
     * Portada: si hay sesión abierta va al inicio, si no, al login.
     */
    public function index()
    {
        if ($this->current_user) {
            redirect('inicio');
            return;
        }

        redirect('login');
    }

    /**
     * Formulario de login y validación de las credenciales.
     */
    public function login()
    {
        // Si ya está conectado no tiene sentido volver al login.
        if ($this->current_user) {
            redirect('inicio');
            return;
        }

        $data = array('error' => '');

        if ($this->input->method(TRUE) === 'POST') {
            $username = trim($this->posted('username', ''));

            // La contraseña no se filtra ni se recorta: es texto exacto.
            $password = (string) $this->input->post('password');

            // El modelo ya descarta cuentas inactivas o eliminadas.
            $user = $this->User_model->find_active_by_username($username);

            // password_verify() es la forma segura de comparar hashes.
            if ($user && password_verify($password, $user['password_hash'])) {
                // Se renueva el id de sesión para evitar el secuestro
                // de sesión y se guarda el usuario como dato de sesión.
                $this->session->sess_regenerate(TRUE);

                $this->session->set_userdata('auth_user', array(
                    'id'        => (int) $user['id'],
                    'username'  => $user['username'],
                    'full_name' => $user['full_name'],
                    'role_code' => $user['role_code'],
                    'role_name' => $user['role_name'],
                ));

                // Registro de la última entrada.
                $this->User_model->touch_last_login($user['id']);

                redirect('inicio');
                return;
            }

            // Mensaje genérico: no revela si el usuario existe o no.
            $data['error'] = 'Usuario o contraseña incorrectos.';
        }

        $this->load->view('auth/login', $data);
    }

    /**
     * Cierre de sesión. Solo por formulario, para no poder abrirlo con un enlace.
     */
    public function logout()
    {
        $this->require_post();

        $this->session->sess_destroy();

        redirect('login');
    }
}
