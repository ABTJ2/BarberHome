<?php
defined('BASEPATH') OR exit('No direct script access allowed');
function h($value){ return html_escape((string)$value); }
function money($value){ return '$'.number_format((float)$value, 2, ',', '.'); }
function status_label($status){ $m=array('reserved'=>'Reservado','attended'=>'Atendido','cancelled'=>'Cancelado','no_show'=>'Ausente'); return isset($m[$status])?$m[$status]:ucfirst($status); }
function status_class($status){ $m=array('reserved'=>'green','attended'=>'blue','cancelled'=>'red','no_show'=>'gold'); return isset($m[$status])?$m[$status]:'neutral'; }
function role_label($code){ return $code==='encargado'?'Encargado':'Recepcionista'; }
function day_name($n){ $d=array(1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'); return isset($d[$n])?$d[$n]:''; }
function flash_message(){ $CI=&get_instance(); if($m=$CI->session->flashdata('success')) return '<div class="alert success">'.h($m).'</div>'; if($m=$CI->session->flashdata('error')) return '<div class="alert error">'.h($m).'</div>'; return ''; }
// Cambia la URL del recurso al editarlo para que el navegador no use una versión vieja.
function asset_url($path){ return base_url($path).'?v='.filemtime(FCPATH.$path); }
function valid_day($value){ if(!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) return FALSE; $d=DateTime::createFromFormat('!Y-m-d',$value); return $d && $d->format('Y-m-d')===$value; }
function valid_clock($value){ return is_string($value) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$value)===1; }
function valid_money($value,$positive=FALSE){ return is_scalar($value) && preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D',(string)$value)===1 && (!$positive || (float)$value>0); }
function valid_percent($value){ return is_scalar($value) && preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',(string)$value)===1 && (float)$value<=100; }
