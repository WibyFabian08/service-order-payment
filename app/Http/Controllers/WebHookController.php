<?php

namespace App\Http\Controllers;

use App\Order;
use App\PaymentLog;
use Illuminate\Http\Request;

class WebHookController extends Controller
{
    public function midtransHandler(Request $request) {
        // tampung data dari response midtrans
        $data = $request->all();

        // get signatur key
        $signatureKey = $data['signature_key'];

        // get data unutk buat my signatureKey
        $order_id = $data['order_id'];
        $status_code = $data['status_code'];
        $gross_amount = $data['gross_amount'];
        $serverKey = env('MIDTRANS_SERVER_KEY');

        // decode dignature key dari data diatas
        $mySignatureKey = hash('sha512', $order_id.$status_code.$gross_amount.$serverKey);

        // get transactions_status, payment_type dan fraud_status
        $transaction_status = $data['transaction_status'];
        $payment_type = $data['payment_type'];
        $fraud_status = $data['fraud_status'];

        if($mySignatureKey !== $signatureKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'invalid signature'
            ], 400);
        }

        // get order_id untuk cek ada atau tidak order id di db
        $realOrderId = explode('-', $order_id);
        $order = Order::find($realOrderId[0]);

        // cek ada tidak order berdasar order_id
        if(!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'order not found'
            ], 404);
        }

        // cek status yang dikiirm midtrans
        if($transaction_status === 'capture') {
            if($fraud_status === 'challenge') {
                $order->status = 'challenge';
            } else {
                $order->status = 'success';
            }
        }

        else if($transaction_status === 'settlement') {
            $order->status = 'success';
        }

        else if($transaction_status === 'cancel' || $transaction_status === 'deny' || $transaction_status === 'expire') {
            $order->status = 'failure';
        }

        else if($transaction_status === 'pending') {
            $order->status = 'pending';
        }

        // get data untuk insert ke tabel log data
        $logData = [
            'status' => $transaction_status,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $payment_type
        ];  

        // insert data ke tabel paymentLog
        PaymentLog::create($logData);

        // save status order baru
        $order->save(); 

        // jika order status success maka masukan user_id dan course_id ke tabel myCourse
        if($order->status === 'success') {
            createPremiumAccess([
                'user_id' => $order->user_id,
                'course_id' => $order->course_id
            ]);
        }

        return response()->json('Ok');

    }
}
