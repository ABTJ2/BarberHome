<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador del catálogo de servicios (solo Encargado).
 *
 * El precio y la duración se guardan como snapshot en cada turno,
 * por lo que cambiar un servicio acá no altera los turnos ya cargados.
 */
class Services extends MY_Controller {

    public function __construct()
    {
        parent::__construct();

        $this->require_manager();

        $this->load->model('Service_model');
    }

    /**
     * Listado con buscador y filtro de eliminados.
     */
    public function index()
    {
        $q = trim((string) $this->input->get('q', TRUE));
        $archived = $this->input->get('archived') === '1';

        $this->render('services/index', array(
            'services' => $this->Service_model->all(FALSE, $archived, $q),
            'q'        => $q,
            'archived' => $archived,
        ), 'Servicios');
    }

    public function create()
    {
        $this->render('services/form', array(
            'service' => NULL,
            'errors'  => array(),
        ), 'Nuevo servicio');
    }

    /**
     * Validación del formulario.
     */
    private function validate()
    {
        $errors = array();

        if (trim($this->posted('name', '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        // Un servicio puede ser gratuito, por eso no se exige precio positivo.
        if (!valid_money($this->posted('price'))) {
            $errors[] = 'El precio debe ser válido y no negativo.';
        }

        // La duración se usa para calcular el fin del turno:
        // debe ser un entero de 1 a 9999 minutos.
        $duration = $this->posted('duration_minutes');
        if (!is_scalar($duration) || !preg_match('/^[1-9][0-9]{0,3}$/D', (string) $duration)) {
            $errors[] = 'La duración debe ser mayor a 0.';
        }

        return $errors;
    }

    /**
     * Datos del servicio que van a la tabla services.
     */
    private function data()
    {
        return array(
            'name'            => trim($this->posted('name', '')),
            'price'           => (float) $this->posted('price', 0),
            'duration_minutes'=> (int) $this->posted('duration_minutes', 0),
            'active'          => $this->posted('active', 0) ? 1 : 0,
            'updated_at'      => date('Y-m-d H:i:s'),
        );
    }

    /**
     * Alta de servicio.
     */
    public function store()
    {
        $this->require_post();

        $errors = $this->validate();

        if ($errors) {
            $this->render('services/form', array(
                'service'=> $this->input->post(NULL, TRUE),
                'errors' => $errors,
            ), 'Nuevo servicio');
            return;
        }

        $data = $this->data();
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->Service_model->create($data);

        $this->session->set_flashdata('success', 'Servicio creado.');
        redirect('servicios');
    }

    public function edit($id)
    {
        $service = $this->Service_model->find($id);

        if (!$service) {
            show_404();
        }

        $this->render('services/form', array(
            'service'=> $service,
            'errors' => array(),
        ), 'Editar servicio');
    }

    /**
     * Edición de servicio.
     */
    public function update($id)
    {
        $this->require_post();

        $service = $this->Service_model->find($id);

        if (!$service) {
            show_404();
        }

        $errors = $this->validate();

        if ($errors) {
            $this->render('services/form', array(
                'service'=> array_merge($service, $this->input->post(NULL, TRUE)),
                'errors' => $errors,
            ), 'Editar servicio');
            return;
        }

        $this->Service_model->update_service($id, $this->data());

        $this->session->set_flashdata('success', 'Servicio actualizado.');
        redirect('servicios');
    }

    /**
     * Baja lógica de servicio.
     */
    public function delete($id)
    {
        $this->require_post();

        if (!$this->Service_model->find($id)) {
            show_404();
        }

        // Si el servicio está en turnos futuros hay que resolverlos antes:
        // un turno confirmado no puede quedar sin servicio.
        if ($this->Service_model->has_future_appointments($id)) {
            $this->session->set_flashdata('error', 'El servicio está en turnos futuros. Modificalos o cancelalos antes de eliminarlo.');
        } else {
            $this->Service_model->archive($id);
            $this->session->set_flashdata('success', 'Servicio eliminado de la lista. Los precios históricos se conservaron.');
        }

        redirect('servicios');
    }

    /**
     * Restaura un servicio eliminado.
     */
    public function restore($id)
    {
        $this->require_post();

        // Solo se restaura si estaba efectivamente eliminado.
        if (!$this->Service_model->is_archived($id)) {
            show_404();
        }

        $this->Service_model->restore($id);

        $this->session->set_flashdata('success', 'Servicio restaurado.');
        redirect('servicios?archived=1');
    }
}
