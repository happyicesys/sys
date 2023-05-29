<?php

namespace App\Interfaces;

interface PaymentGateway
{
  public function createPayment($params);
  public function getOrderId();
  public function getReferenceId();
  // public function createCharge();
  // public function generateQrCode($orderId);
}