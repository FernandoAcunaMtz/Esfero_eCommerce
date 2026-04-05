<?php
/**
 * Maneja la cancelación del pago de PayPal
 */
session_start();

$_SESSION['error_message'] = 'El pago fue cancelado. Puedes intentar de nuevo cuando estés listo.';
header('Location: checkout.php?canceled=1');
exit;

