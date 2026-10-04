<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Accounting extends MY_Controller {
    public function __construct(){
        parent::__construct();
        $this->require_manager();
        $this->load->model(array('Payment_model','Expense_model'));
    }

    private function selected_range(){
        $from=$this->input->get('from',TRUE) ?: date('Y-m-01');
        $to=$this->input->get('to',TRUE) ?: date('Y-m-d');
        if(!valid_day($from)) $from=date('Y-m-01');
        if(!valid_day($to)) $to=date('Y-m-d');
        if($from>$to){ $temporary=$from; $from=$to; $to=$temporary; }
        return array($from,$to);
    }

    public function index(){
        list($from,$to)=$this->selected_range();
        $income=$this->Payment_model->total_between($from,$to);
        $expenses=$this->Expense_model->total_between($from,$to);
        $commissions=$this->Payment_model->commissions_between($from,$to);

        // La liquidación siempre usa los importes efectivamente cobrados.
        $this->render('accounting/index',array(
            'from'=>$from,'to'=>$to,
            'income'=>$income,
            'expenses_total'=>$expenses,
            'commissions'=>$commissions,
            'result'=>$income-$expenses-$commissions,
            'payments'=>$this->Payment_model->between($from,$to),
            'expenses'=>$this->Expense_model->all_between($from,$to),
            'production'=>$this->Payment_model->liquidation_between($from,$to),
            'categories'=>$this->Expense_model->categories()
        ),'Contabilidad');
    }

    public function expenses(){
        // En Egresos la búsqueda inicial abarca todo el historial, no solo el mes actual.
        $from=(string)$this->input->get('from',TRUE);
        $to=(string)$this->input->get('to',TRUE);
        if(!valid_day($from))$from='';
        if(!valid_day($to))$to='';
        $q=trim((string)$this->input->get('q',TRUE));
        $archived=$this->input->get('archived')==='1';
        $this->render('accounting/expenses',array(
            'from'=>$from,'to'=>$to,'q'=>$q,'archived'=>$archived,
            'expenses'=>$this->Expense_model->all_between($from ?: '1000-01-01',$to ?: '9999-12-31',$q,$archived),
            'categories'=>$this->Expense_model->categories()
        ),'Egresos');
    }

    private function expense_input(){
        $date=$this->posted('expense_date');
        $category=(int)$this->posted('category_id');
        $concept=trim($this->posted('concept',''));
        $amount=$this->posted('amount');
        if(!valid_day($date) || !$this->Expense_model->valid_category($category)
            || $concept==='' || !valid_money($amount,TRUE)) return FALSE;
        return array('expense_date'=>$date,'category_id'=>$category,'concept'=>$concept,
            'amount'=>$amount,'updated_at'=>date('Y-m-d H:i:s'));
    }

    public function store_expense(){
        $this->require_post();
        $data=$this->expense_input();
        if(!$data){
            $this->session->set_flashdata('error','Completá fecha válida, categoría activa, concepto e importe positivo.');
            redirect('egresos');
            return;
        }
        $data['created_by']=$this->current_user['id'];
        $data['created_at']=date('Y-m-d H:i:s');
        $this->Expense_model->create($data);
        $this->session->set_flashdata('success','Egreso registrado.');
        redirect('egresos');
    }

    public function edit_expense($id){
        $expense=$this->Expense_model->find($id);
        if(!$expense || $expense['voided_at']) show_404();
        $this->render('accounting/expense_form',array('expense'=>$expense,
            'categories'=>$this->Expense_model->categories(),'errors'=>array()),'Corregir egreso');
    }

    public function update_expense($id){
        $this->require_post();
        $expense=$this->Expense_model->find($id);
        if(!$expense || $expense['voided_at']) show_404();
        $data=$this->expense_input();
        if(!$data){
            $this->render('accounting/expense_form',array(
                'expense'=>array_merge($expense,$this->input->post(NULL,TRUE)),
                'categories'=>$this->Expense_model->categories(),
                'errors'=>array('Revisá los datos del egreso.')
            ),'Corregir egreso');
            return;
        }
        $this->Expense_model->update_expense($id,$data);
        $this->session->set_flashdata('success','Egreso corregido.');
        redirect('egresos');
    }

    public function void_expense($id){
        $this->require_post();
        $expense=$this->Expense_model->find($id);
        if(!$expense || $expense['voided_at']) show_404();
        // Anular conserva la fila y su autor, pero la excluye de los totales.
        $this->Expense_model->void_expense($id,$this->current_user['id']);
        $this->session->set_flashdata('success','Egreso anulado.');
        redirect('egresos');
    }
    public function restore_expense($id){
        $this->require_post();
        $expense=$this->Expense_model->find($id);
        if(!$expense || !$expense['voided_at'])show_404();
        $this->Expense_model->restore_expense($id);
        $this->session->set_flashdata('success','Egreso restaurado.');
        redirect('egresos?archived=1');
    }
}
