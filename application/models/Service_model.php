<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Modelo del catálogo de servicios.
 */
class Service_model extends CI_Model {

    /**
     * Listado de servicios.
     *
     * @param bool   $active_only solo los activos (los que se pueden agendar)
     * @param bool   $archived   true = eliminados; false = vigentes
     * @param string $q          texto para filtrar por nombre
     */
    public function all($active_only = FALSE, $archived = FALSE, $q = '')
    {
        $this->db->from('services');

        // Borrado lógico: los eliminados se conservan para el historial.
        $this->db->where($archived ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL', NULL, FALSE);

        if ($active_only) {
            $this->db->where('active', 1);
        }

        if ($q !== '') {
            $this->db->like('name', $q);
        }

        return $this->db->order_by('name')->get()->result_array();
    }

    /**
     * Un servicio por su id, o NULL si no existe o fue eliminado.
     */
    public function find($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('services')
            ->row_array();
    }

    /**
     * Busca varios servicios activos a la vez.
     *
     * Se usa al validar un turno: la cantidad devuelta tiene que coincidir
     * con la cantidad pedida, así se detecta un servicio inexistente.
     */
    public function get_many($ids)
    {
        if (!$ids) {
            return array();
        }

        return $this->db->where_in('id', array_map('intval', $ids))
            ->where('active', 1)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->get('services')
            ->result_array();
    }

    /**
     * ¿Este servicio está siendo usado por algún turno futuro reservado?
     *
     * Mientras un servicio está en uso no se puede eliminar de la lista:
     * ese turno se quedaría sin el servicio que lo compone.
     *
     * @param  mixed $id id del servicio
     * @return bool
     */
    public function has_future_appointments($id)
    {
        return $this->db->from('appointment_services x')
            ->join('appointments a', 'a.id = x.appointment_id')
            ->where('x.service_id', (int) $id)
            ->where('a.status', 'reserved')
            ->where('a.start_at >=', date('Y-m-d H:i:s'))
            ->count_all_results() > 0;
    }

    /**
     * ¿El servicio está eliminado (para poder restaurarlo)?
     *
     * @param  mixed $id id del servicio
     * @return bool
     */
    public function is_archived($id)
    {
        return $this->db->where('id', (int) $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->count_all_results('services') > 0;
    }

    /**
     * Baja lógica: el servicio sale de los listados, pero los turnos
     * antiguos conservan su precio snapshot.
     */
    public function archive($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NULL', NULL, FALSE)
            ->update('services', array(
                'active'     => 0,
                'deleted_at' => date('Y-m-d H:i:s'),
            ));
    }

    /**
     * Restaura un servicio eliminado.
     */
    public function restore($id)
    {
        return $this->db->where('id', $id)
            ->where('deleted_at IS NOT NULL', NULL, FALSE)
            ->update('services', array(
                'active'     => 1,
                'deleted_at' => NULL,
            ));
    }

    /**
     * Alta de servicio. Devuelve el id generado.
     */
    public function create($data)
    {
        $this->db->insert('services', $data);

        return $this->db->insert_id();
    }

    /**
     * Actualización de servicio.
     */
    public function update_service($id, $data)
    {
        return $this->db->where('id', $id)->update('services', $data);
    }
}
