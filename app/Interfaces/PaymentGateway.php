<?php

namespace App\Interfaces;

interface PaymentGateway
{
  public function createPayment($amount, $currency);
  public function generateQrCode($orderId);
}