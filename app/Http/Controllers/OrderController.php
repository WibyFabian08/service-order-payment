<?php

namespace App\Http\Controllers;

use App\Order;
use Midtrans\Snap;
use Midtrans\Config;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request) {
        $userId = $request->input('user_id');

        $orders = Order::where('user_id', $userId)->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    public function create(Request $request) {
        // get request user_id dan course_id
        $user = $request->input('user');
        $course = $request->input('course');

        // lalu insert ke tabel orders
        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id']
        ]);

        // buat transaction details, itemDetails dan customerDetails unutk midtrans params
        $transactionDetails = [
            // buat id dengan string random agar tidak duplikat
            'order_id' => $order->id.'-'.Str::random(5),
            'gross_amount' => (int)$course['price']
        ];

        $itemDetails = [
            [
                'id' => $course['id'],
                'price' => (int)$course['price'],
                'quantity' => 1,
                'name' => $course['name'],
                'brand' => 'UwebShop',
                'category' => 'Online Course'
            ]
        ];

        $customerDetails = [
            'first_name' => $user['name'],
            'email' => $user['email']
        ];  

        // buat midtrans params unutk get snap url
        $midtransParams = [
            'transaction_details' => $transactionDetails,
            'item_details' => $itemDetails,
            'customer_details' => $customerDetails
        ];

        // get snap url
        $midtransSnapUrl = $this->getSnapUrl($midtransParams);

        $order->snap_url = $midtransSnapUrl;

        $order->meta_data = [
          'course_id' => $course['id'],
          'user_id' => $user['id'],
          'course_name' => $course['name'],
          'course_thumbnail' => $course['thumbnail'],
          'course_level' => $course['level']  
        ];

        $order->save();

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    protected function getSnapUrl($params) {
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$clientKey = config('services.midtrans.clientKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        $snapUrl = Snap::createTransaction($params)->redirect_url;

        return $snapUrl;
    }
}
