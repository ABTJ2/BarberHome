<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo de usuarios y perfiles.
 */
class User_model extends CI_Model {

    /**
     * Busca un usuario activo por nombre de acceso.
     *
     * Trae el código del perfil (role_code) porque los permisos se
     * comparan contra ese valor.
     */
    public function find_active_by_username($username)
    {
        return $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.username', $username)
            ->where('u.active', 1)
            ->where('u.deleted_at IS NULL', NULL, FALSE)
            ->get()
            ->row_array();
    }

    /**
     * Listado de usuarios.
     *
     * @param string $q        texto para filtrar por usuario o nombre
     * @param bool   $archived true = eliminados; false = vigentes
     */
    public function all($q = '', $archived = FALSE)
    {
        $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id');

        // Borrado lógico: los eliminados se conservan para la auditoría.
        $this->db->where($archived ? 'u.deleted_at IS NOT NULL' : 'u.deleted_at IS NULL', NULL, FALSE);

        if ($q !== '') {
            $this->db->group_start()
                ->like('u.username', $q)
                ->or_like('u.full_name', $q)
                ->group_end();
        }

        return $this->db->order_by('u.full_name')->get()->result_array();
    }

    /**
     * Lista de perfiles disponibles.
     */
    public function roles()
    {
        return $this->db->order_by('id')->get('roles')->result_array();
    }

    /**
     * Un usuario por su id, o NULL si no existe o fue eliminado.
     */
    public function find($id)
    {
        return $this->db->select('u.*, r.code role_code, r.name role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)
            ->where('u.deleted_at IS NULL', NULL, FALSE)
            ->get()
            ->row_array();
    }

    /**
     * ¿El nombre de usuario ya existe?
     *
     * @param mixed $exclude_id usuario a ignorar (en edición)
     */
    public function username_exists($username, $exclude_id = NULL)
    {
        $this->db->where('username', $username);

        if ($exclude_id) {
            $this->db->where('id !=', $exclude_id);
        }

        return $this->db->count_all_results('users') > 0;
    }

    /**
     * ¿El usuario está eliminado (para poder restaurarlo)?
     */
    public function is_archived($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('users') > 0;
    }

    /**
     * Registra la hora de la última entrada al sistema.
     */
    public function touch_last_login($id)
    {
        return $this->db->where('id', (int) $id)
            ->update('users', array('last_login_at' => date('Y-m-d H:i:s')));
    }

    /**
     * Baja lógica del usuario y además desactiva su acceso.
     */
    public function archive($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('users', array(
                'active'     => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * Restaura un usuario eliminado.
     */
    public function restore($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('users', array(
                'active'     => 1,
                'deleted_at' => NULL,
            ));
    }

    /**
     * Alta de usuario. Devuelve el id generado.
     */
    public function create($data)
    {
        $this->db->insert('users', $data);

        return $this->db->insert_id();
    }

    /**
     * Actualización de usuario.
     */
    public function update_user($id, $data)
    {
        return $this->db->where('id', $id)->update('users', $data);
    }
}
