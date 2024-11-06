<?php

namespace App\Http\Controllers;
use Exception;


use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use NunoMaduro\Collision\Provider;
use Unicodeveloper\Paystack\Paystack;

class PaystackController extends Controller
{
    //
    public function redirectToGateway(Request $request)
    {
        //dd("we got here");
        $orderId = $request->order_id;
       // Get the current user's cart items
        $cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->get();

        $total = 0;
        $orderItems = [];
        
        // Prepare each item for payment description
        foreach ($cart as $item) {
            $product = Product::find($item->product_id);

            $orderItems[] = [
                'name' => $product->title,
                'price' => $item->price,
                'description' => 'Payment for ' . $product->title,
                'quantity' => $item->quantity,
            ];
            
            // Calculate total price
            $total += $item->price * $item->quantity;
        }

        // Apply discount if a coupon is present in session
        if (session('coupon')) {
            $discount = session('coupon')['value'];
            $total -= $discount;
        }

        // Multiply by 100 to convert to kobo for Paystack
        $amountInKobo = $total * 100;

        // Generate an order reference
        $orderReference = 'ORD-' . strtoupper(uniqid());

        // Store this order reference to link it with the callback
        Cart::where('user_id', auth()->user()->id)->where('order_id', null)->update(['order_id' => session()->get('id')]);

        $paystack = new paystack();

        // Prepare data for the Paystack transaction
        $data = [
            'amount' => $amountInKobo,
            'email' => auth()->user()->email,
            'reference' => $orderReference,
            'callback_url' => route('paystack.callback'),
        ];

        try {
            // Redirect to Paystack payment authorization URL
            $authorization = $paystack->getAuthorizationUrl($data)->redirectNow();
            return $authorization;
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Payment initiation failed. Please try again.');
        }       
    }

    /**
     * Obtain Paystack payment information
     * @return void
     */
    public function handleGatewayCallback()
    {
        $paystack = new paystack();

        $paymentDetails = $paystack->getPaymentData();

        dd($paymentDetails);
        // Now you have the payment details,
        // you can store the authorization_code in your db to allow for recurrent subscriptions
        // you can then redirect or do whatever you want
    }
}
