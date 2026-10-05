<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador base de la aplicación.
 *
 * Todos los controladores heredan de esta clase, así el proyecto
 * tiene un único lugar donde se resuelve:
 * - quién es el usuario con sesión;
 * - los permisos por perfil;
 * - el dibujado de la pantalla (header + sidebar + vista + footer);
 * - la lectura de datos de formulario.
 */
class MY_Controller extends CI_Controller {

    // Registros por página en los listados largos (historial de turnos,
    // cobros, egresos y clientes).
    const REGISTROS_POR_PAGINA = 25;

    // Datos del usuario con sesión, o NULL si no hay nadie conectado.
    protected $current_user = NULL;

    public function __construct()
    {
        parent::__construct();

        $this->current_user = $this->session->userdata('auth_user');

        // Se relee el usuario en cada petición para reflejar cambios hechos
        // en otra sesión: por ejemplo, si un Encargado lo desactivó.
        if ($this->current_user) {
            $this->load->model('User_model');

            $user = $this->User_model->find((int) $this->current_user['id']);

            if (!$user || !$user['active']) {
                // La cuenta ya no sirve: se cierra la sesión.
                $this->session->unset_userdata('auth_user');
                $this->current_user = NULL;
            } else {
                // En la sesión solo queda lo necesario, nunca el hash.
                $this->current_user = array(
                    'id'         => (int) $user['id'],
                    'username'   => $user['username'],
                    'full_name'  => $user['full_name'],
                    'role_code'  => $user['role_code'],
                    'role_name'  => $user['role_name'],
                );

                $this->session->set_userdata('auth_user', $this->current_user);
            }
        }

        // Las vistas (sidebar, header) necesitan saber quién está conectado.
        $this->load->vars('current_user', $this->current_user);
    }

    /**
     * Exige que haya un usuario con sesión.
     */
    protected function require_login()
    {
        if (!$this->current_user) {
            $this->session->set_flashdata('error', 'Iniciá sesión para continuar.');
            redirect('login');
            exit;
        }
    }

    /**
     * Exige el perfil Encargado (administración del local).
     */
    protected function require_manager()
    {
        $this->require_login();

        if (($this->current_user['role_code'] ?? '') !== 'encargado') {
            show_error('No tenés permisos para acceder a esta sección.', 403, 'Acceso restringido');
            exit;
        }
    }

    /**
     * Dibuja una pantalla completa: cabecera, menú lateral, vista y pie.
     *
     * @param string $view  archivo de application/views
     * @param array  $data  datos para la vista
     * @param string $title título de la página
     */
    protected function render($view, $data = array(), $title = 'Barber House')
    {
        $data['page_title'] = $title;

        $this->load->view('layout/header', $data);
        $this->load->view('layout/sidebar', $data);
        $this->load->view($view, $data);
        $this->load->view('layout/footer', $data);
    }

    /**
     * Datos de paginación de un listado.
     *
     * Se llama después de contar los registros y antes de consultarlos: de
     * esa forma la página pedida ya está corregida y el desplazamiento que
     * se le pasa al modelo nunca se pasa.
     *
     * @param int $total cuántos registros cumplen los filtros
     *
     * @return array page, pages, total, limit, offset, first, last
     */
    protected function paginacion($total)
    {
        $total  = (int) $total;
        $limite = self::REGISTROS_POR_PAGINA;
        $paginas = max(1, (int) ceil($total / $limite));

        // Una página inexistente (0, -3, 9999) se corrige en vez de romper.
        $pagina = min(max(1, (int) $this->input->get('page')), $paginas);

        return array(
            'page'  => $pagina,
            'pages' => $paginas,
            'total' => $total,
            'limit' => $limite,
            'offset'=> ($pagina - 1) * $limite,
            'first' => ($pagina - 1) * $limite + 1,
            'last'  => min($total, $pagina * $limite),
        );
    }

    /**
     * Lee un dato de formulario.
     *
     * El TRUE de $this->input->post() aplica el filtro XSS de CodeIgniter.
     *
     * @param string $key     nombre del campo
     * @param mixed  $default valor si el campo no viene
     */
    protected function posted($key, $default = NULL)
    {
        $value = $this->input->post($key, TRUE);

        return $value === NULL ? $default : $value;
    }

    /**
     * Exige que la operación venga de un formulario (POST).
     * Evita que un enlace mal puesto en el navegador ejecute un borrado.
     */
    protected function require_post()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Operación disponible solo mediante formulario.', 405);
        }
    }
}
