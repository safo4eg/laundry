⚠️ You have unpaid orders!

@foreach($payments as $payment)
    ‼️ Order <b>#{{$payment->order->id}}</b> - Not paid
@endforeach
@if(!$is_one)

    Select the required order you want to pay for.
@endif

    If you want to pay later, click "Continue".
