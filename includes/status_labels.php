<?php
$statusLabels = [
    'pending' => ['label' => 'Pendente', 'class' => 'status-pending'],
    'paid'    => ['label' => 'Pago',     'class' => 'status-active'],
    'shipped' => ['label' => 'Enviado',  'class' => 'status-processing'],
    'delivered'=> ['label' => 'Concluído','class' => 'status-active'],
    'canceled'=> ['label' => 'Cancelado','class' => 'status-inactive'],
];

$statusLabelsFlat = array_column($statusLabels, 'label');

$paymentStatusLabels = [
    'pending'    => ['label' => 'Aguardando pagamento', 'class' => 'status-pending'],
    'processing' => ['label' => 'Processando',          'class' => 'status-processing'],
    'paid'       => ['label' => 'Pago',                 'class' => 'status-active'],
    'failed'     => ['label' => 'Falha no pagamento',   'class' => 'status-inactive'],
    'expired'    => ['label' => 'Expirado',             'class' => 'status-inactive'],
    'refunded'   => ['label' => 'Estornado',            'class' => 'status-inactive'],
];
