<?php
$statusLabels = [
    'pending' => ['label' => 'Pendente', 'class' => 'status-pending'],
    'paid'    => ['label' => 'Pago',     'class' => 'status-active'],
    'shipped' => ['label' => 'Enviado',  'class' => 'status-processing'],
    'delivered'=> ['label' => 'Concluído','class' => 'status-active'],
    'canceled'=> ['label' => 'Cancelado','class' => 'status-inactive'],
];

$statusLabelsFlat = array_column($statusLabels, 'label');
